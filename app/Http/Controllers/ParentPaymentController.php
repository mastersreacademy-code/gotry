<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Interest;
use App\Services\PayPalSandboxCheckout;
use App\Services\TrialSchedule;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ParentPaymentController
{
    public function start(Request $request, Interest $interest, PayPalSandboxCheckout $paypal)
    {
        $this->authorizeParent($request, $interest);
        $payment = DB::transaction(function () use ($interest) {
            [$current, $activity] = $this->lockRegistration($interest);
            $payment = $this->payment($current);
            abort_if($payment?->paid_at, 409, 'This registration is already paid.');
            $this->ensurePayable($current, $activity);

            if (! $payment || ! $this->matchesVersion($payment, $current, $activity)) {
                DB::table('trial_payments')->updateOrInsert(['interest_id' => $current->id], [
                    'attempt' => (string) Str::uuid(),
                    'interest_version' => $current->version,
                    'activity_version' => $activity->version,
                    'order_id' => null,
                ]);
            }

            return $this->payment($current);
        });

        try {
            $order = $paypal->createOrder(
                $payment->attempt,
                $payment->attempt,
                route('parent.payment.return', $interest),
                route('parent.dashboard'),
            );
        } catch (HttpClientException|RuntimeException) {
            return $this->paymentError();
        }

        $orderId = $order['id'] ?? null;
        $approval = collect($order['links'] ?? [])->first(fn ($link) => in_array($link['rel'] ?? null, ['payer-action', 'approve'], true));
        $url = $approval['href'] ?? '';
        if (! $this->validId($orderId) || ! is_string($url) || ! str_starts_with($url, 'https://www.sandbox.paypal.com/')) {
            return $this->paymentError();
        }

        DB::transaction(function () use ($interest, $payment, $orderId): void {
            [$current, $activity] = $this->lockRegistration($interest);
            $stored = $this->payment($current);
            $this->ensurePayable($current, $activity);
            abort_unless($stored && $stored->attempt === $payment->attempt && ! $stored->paid_at
                && $this->matchesVersion($stored, $current, $activity)
                && ($stored->order_id === null || $stored->order_id === $orderId), 409, 'Checkout changed. Return to your dashboard.');
            DB::table('trial_payments')->where('interest_id', $current->id)->update(['order_id' => $orderId]);
        });

        return redirect()->away($url);
    }

    public function returned(Request $request, Interest $interest)
    {
        $this->authorizeParent($request, $interest);
        $payment = $this->payment($interest);
        abort_unless($payment?->order_id && $payment->order_id === $request->query('token'), 404);

        return redirect()->route('parent.dashboard')->with('payment_approval', $payment->attempt);
    }

    public function capture(Request $request, Interest $interest, PayPalSandboxCheckout $paypal)
    {
        $this->authorizeParent($request, $interest);
        $attempt = $request->validate(['attempt' => ['required', 'uuid']])['attempt'];

        try {
            $paid = DB::transaction(function () use ($interest, $attempt, $paypal): bool {
                [$current, $activity] = $this->lockRegistration($interest);
                $payment = $this->payment($current);
                abort_unless($payment?->order_id && $payment->attempt === $attempt, 404);
                if ($payment->paid_at) {
                    return true;
                }
                $this->ensurePayable($current, $activity);
                abort_unless($this->matchesVersion($payment, $current, $activity), 409, 'Trial changed. Start checkout again.');

                $order = $paypal->captureOrder($payment->order_id, md5('capture:'.$payment->attempt));
                $units = $order['purchase_units'] ?? [];
                $captures = $units[0]['payments']['captures'] ?? [];
                $capture = $captures[0] ?? [];
                if (($order['id'] ?? null) !== $payment->order_id || ($order['status'] ?? null) !== 'COMPLETED'
                    || count($units) !== 1 || ($units[0]['reference_id'] ?? null) !== $payment->attempt
                    || count($captures) !== 1 || ($capture['status'] ?? null) !== 'COMPLETED'
                    || ($capture['amount']['currency_code'] ?? null) !== 'AUD' || ($capture['amount']['value'] ?? null) !== '2.00'
                    || ! $this->validId($capture['id'] ?? null)) {
                    return false;
                }

                DB::table('trial_payments')->where('interest_id', $current->id)->update([
                    'capture_id' => $capture['id'],
                    'paid_at' => now(),
                ]);

                return true;
            });
        } catch (HttpClientException|RuntimeException $error) {
            if ($error instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                throw $error;
            }

            return $this->paymentError($attempt);
        }

        return $paid
            ? redirect()->route('parent.dashboard')->with('status', 'PayPal Sandbox payment verified.')
            : $this->paymentError($attempt);
    }

    private function authorizeParent(Request $request, Interest $interest): void
    {
        abort_unless($interest->parent_id === $request->user()->id, 404);
    }

    private function lockRegistration(Interest $interest): array
    {
        $activity = Activity::query()->lockForUpdate()->findOrFail($interest->activity_id);
        $current = Interest::query()->lockForUpdate()->findOrFail($interest->id);

        return [$current, $activity];
    }

    private function payment(Interest $interest): ?object
    {
        return DB::table('trial_payments')->where('interest_id', $interest->id)->first();
    }

    private function matchesVersion(object $payment, Interest $interest, Activity $activity): bool
    {
        return (int) $payment->interest_version === $interest->version
            && (int) $payment->activity_version === $activity->version;
    }

    private function ensurePayable(Interest $interest, Activity $activity): void
    {
        $status = $activity->status === 'pending_registrations'
            ? TrialSchedule::status($activity, $interest->trial_session)
            : $activity->status;
        abort_unless($interest->status === 'interested' && $status === 'proceeding_pending_payments'
            && $activity->provider?->is_active, 409, 'This trial registration is no longer eligible for payment.');
    }

    private function validId(mixed $id): bool
    {
        return is_string($id) && preg_match('/\A[A-Z0-9]{1,64}\z/', $id) === 1;
    }

    private function paymentError(?string $attempt = null)
    {
        return redirect()->route('parent.dashboard')->with('payment_approval', $attempt)
            ->withErrors(['payment' => 'GoTry could not verify the PayPal Sandbox payment. Please retry confirmation if available.']);
    }
}
