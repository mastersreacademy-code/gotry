<?php

namespace App\Services;

use App\Models\Interest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ParentPortalSummary
{
    public function forParent(User $parent): array
    {
        $interests = Interest::where('parent_id', $parent->id)->where('status', 'interested')
            ->with('activity.provider')->get();
        $paid = DB::table('trial_payments')->whereIn('interest_id', $interests->modelKeys())
            ->whereNotNull('paid_at')->pluck('interest_id')->flip();
        $upcoming = null;
        $paymentsDue = [];

        foreach ($interests as $interest) {
            $activity = $interest->activity;
            $status = $activity->status === 'pending_registrations'
                ? TrialSchedule::status($activity, $interest->trial_session) : $activity->status;
            if (! in_array($status, ['pending_registrations', 'proceeding_pending_payments'], true)
                || ! $activity->provider?->is_active) {
                continue;
            }

            $sessions = TrialSchedule::sessions($activity);
            if ($interest->trial_session !== null && ! isset($sessions[$interest->trial_session])) {
                continue;
            }
            $local = $sessions[$interest->trial_session] ?? null;
            if ($local === null && ($activity->details['trial_type'] ?? '') === 'group') {
                $local = ($activity->details['trial_date'] ?? '').' '.($activity->details['trial_time'] ?? '');
            }
            $start = null;
            if ($local !== null) {
                try {
                    $start = TrialSchedule::startsAt($local);
                } catch (InvalidArgumentException) {
                    continue;
                }
                if (! $start->isFuture()) {
                    continue;
                }
            }

            $isPaid = $paid->has($interest->id);
            if ($status === 'proceeding_pending_payments' && ! $isPaid) {
                $paymentsDue[] = $interest;
            }
            if ($isPaid && $status === 'proceeding_pending_payments' && $start && ($upcoming === null || $start->lessThan($upcoming['startsAt']))) {
                $upcoming = [
                    'interest' => $interest,
                    'startsAt' => $start,
                    'label' => $isPaid ? 'Paid' : ($status === 'proceeding_pending_payments' ? 'Ready for payment' : 'Interest registered'),
                ];
            }
        }

        return compact('upcoming', 'paymentsDue');
    }
}
