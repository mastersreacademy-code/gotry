<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ActivityEvent;
use App\Models\InboxNotification;
use App\Models\Interest;
use App\Models\InterestEvent;
use App\Models\OutboxMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

class NotificationOutbox
{
    private function enqueue(string $source, int $recipient, array $payload): void
    {
        if (DB::transactionLevel() < 1) throw new LogicException('Outbox writes require the source transaction.');
        $message = OutboxMessage::firstOrCreate(['source_key' => $source, 'recipient_id' => $recipient], ['id' => (string) Str::uuid(), ...$payload]);
        foreach ($payload as $field => $value) {
            if ((string) $message->getAttribute($field) !== (string) $value) throw new LogicException('Outbox source payload mismatch.');
        }
    }

    public function interest(InterestEvent $event, Interest $interest, Activity $activity): void
    {
        $actor = $event->action === 'offered' ? $activity->provider_id : $interest->parent_id;
        $validActor = ($event->actor_id === null && in_array($event->action, ['offered', 'offer_expired'], true))
            || (int) $event->actor_id === $actor
            || ($event->action === 'offer_expired' && (int) $event->actor_id === $activity->provider_id);
        if ((int) $event->interest_id !== $interest->id || (int) $event->version !== $interest->version
            || ! $validActor || $interest->activity_id !== $activity->id) {
            throw new LogicException('Outbox event does not match interest state.');
        }
        [$title, $parentCopy, $providerCopy] = match ($event->action) {
            'registered' => ['Interest registered', 'Your interest was registered.', 'A new interest was registered.'],
            'withdrawn' => ['Interest withdrawn', 'Your interest was withdrawn.', 'An interest registration was withdrawn.'],
            'transferred' => ['Trial session changed', 'Your interest moved to another session.', 'An interest registration moved to another session.'],
            'waitlisted' => ['Waitlist joined', 'You joined the session waitlist.', 'A family joined the session waitlist.'],
            'offered' => ['Seat offered', 'A seat is reserved for you until '.$event->offer_expires_at?->copy()->setTimezone('Australia/Sydney')->format('Y-m-d H:i T').'. Accept it on your dashboard.', 'A waitlisted family received a time-limited seat offer.'],
            'offer_accepted' => ['Seat offer accepted', 'You accepted the offer and registered interest.', 'A family accepted a seat offer.'],
            'waitlist_left' => ['Waitlist left', 'You left the waitlist or declined the offer.', 'A family left the waitlist or declined an offer.'],
            'offer_expired' => ['Seat offer expired', 'Your seat offer expired.', 'A seat offer expired.'],
            'restored' => ['Interest restored', 'Your interest was restored.', 'An interest registration was restored.'],
            default => throw new LogicException('Unsupported interest notification event.'),
        };
        $context = $this->trialContext($activity, $interest->trial_session, $event->schedule_change);
        $base = ['activity_id' => $activity->id, 'interest_event_id' => $event->id, 'activity_event_id' => null, 'title' => $title];
        $this->enqueue('interest:'.$event->id, $interest->parent_id, [...$base, 'kind' => 'parent_'.$event->action,
            'message' => $parentCopy.$context.' This is not a confirmed booking or payment request.']);
        $this->enqueue('interest:'.$event->id, $activity->provider_id, [...$base, 'kind' => 'provider_'.$event->action,
            'message' => $providerCopy.$context.' No child or parent details are included in this notice.']);
    }

    public function activity(ActivityEvent $event, Activity $activity): void
    {
        $session = $event->trial_session === null ? null : (int) $event->trial_session;
        $status = $session === null ? $activity->status : TrialSchedule::status($activity, $session);
        if ((int) $event->activity_id !== $activity->id || $event->to_status !== $status) throw new LogicException('Outbox event does not match activity state.');
        [$kind, $title, $copy] = match ($event->action) {
            'approve' => ['activity_approved', 'Activity approved', 'Your activity listing was approved. Parents can now register interest.'],
            'reject' => ['activity_rejected', 'Activity rejected', 'Your activity listing was rejected. Open your provider dashboard to review the feedback.'],
            'confirm_minimum' => ['trial_proceeding','Trial proceeding','Minimum interest has been reached. No payment has been collected and this is not proof of a paid booking.'],
            'cancel_low_numbers' => ['trial_cancelled','Trial cancelled','The trial did not reach its required minimum and has been cancelled.'],
            'cancel_admin' => ['trial_cancelled','Trial cancelled','An administrator has cancelled this trial. No payment was collected by GoTry.'],
            'rescheduled' => ['trial_rescheduled', 'Trial rescheduled', 'The trial session time changed. Existing interest registrations stay with this session.'],
            'minimum_lost' => ['trial_review','Trial requires review','Interest is below the required minimum. The trial has returned to registration review.'],
            default => throw new LogicException('Unsupported activity notification event.'),
        };
        $context = $this->trialContext($activity, $session, $event->schedule_change);
        // Feedback/contact details stay on the authorized activity page, not in copied payloads.
        $this->enqueue('activity:'.$event->id, $activity->provider_id, ['activity_id' => $activity->id,
            'interest_event_id' => null, 'activity_event_id' => $event->id, 'kind' => $kind, 'title' => $title,
            'message' => $copy.$context]);
        if (! in_array($event->action,['approve','reject'],true)) {
            $statuses = in_array($event->action, ['rescheduled', 'cancel_admin', 'cancel_low_numbers'], true)
                ? ['interested', 'waitlisted', 'offered']
                : ['interested'];
            $interests = Interest::where('activity_id', $activity->id)->whereIn('status', $statuses);
            if ($session !== null) {
                $interests->where('trial_session', $session);
            } elseif ($event->action === 'cancel_admin' && TrialSchedule::sessions($activity) !== []) {
                $open = array_filter(array_keys(TrialSchedule::sessions($activity)), fn ($number) =>
                    in_array(TrialSchedule::status($activity, $number), ['pending_registrations', 'proceeding_pending_payments'], true));
                $interests->whereIn('trial_session', $open);
            }
            $parents = $interests->distinct()->pluck('parent_id');
            foreach ($parents as $parentId) $this->enqueue('activity:'.$event->id,(int)$parentId,['activity_id'=>$activity->id,
                'interest_event_id'=>null,'activity_event_id'=>$event->id,'kind'=>'parent_'.$kind,'title'=>$title,'message'=>$copy.$context]);
        }
    }

    private function trialContext(Activity $activity, ?int $session, ?array $change): string
    {
        if ($change !== null) {
            return ' Activity: '.$activity->title.'. Previous start: '.$change['from_start'].' (Sydney). New start: '.$change['to_start'].' (Sydney).';
        }
        $start = TrialSchedule::sessions($activity)[$session] ?? null;

        return ' Activity: '.$activity->title.'.'.($start === null ? '' : ' Trial session: '.$start.' (Sydney).');
    }

    public function deliver(User $recipient, int $limit = 50): int
    {
        return DB::transaction(function () use ($recipient, $limit) {
            $messages = OutboxMessage::where('recipient_id', $recipient->id)->whereNull('delivered_at')
                ->orderBy('id')->limit(max(1, min(100, $limit)))->lockForUpdate()->get();
            foreach ($messages as $message) {
                $payload = ['recipient_id' => $message->recipient_id, 'activity_id' => $message->activity_id,
                    'kind' => $message->kind, 'title' => $message->title, 'message' => $message->message,
                    'created_at' => $message->getRawOriginal('created_at')];
                $notice = InboxNotification::firstOrCreate(['outbox_id' => $message->id], ['id' => (string) Str::uuid(), ...$payload]);
                foreach ($payload as $field => $value) {
                    $actual = $field === 'created_at' ? $notice->getRawOriginal($field) : $notice->getAttribute($field);
                    if ((string) $actual !== (string) $value) throw new LogicException('Inbox materialization does not match outbox.');
                }
                // Insertion, exact-match verification and acknowledgement commit together.
                $message->update(['delivered_at' => now()]);
            }
            return $messages->count();
        }, 5);
    }
}
