<?php
namespace App\Services;
use App\Models\{Activity, ActivityEvent, User};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{DB, Validator};

class AdminActivityLifecycle
{
    public function change(User $admin, int $id, array $input): Activity
    {
        abort_unless($admin->role === 'admin' && $admin->is_active,403);
        $data = Validator::make($input,['action'=>['required','in:confirm_minimum,cancel_low_numbers,cancel_admin'],
            'trial_session'=>['nullable','integer','between:1,10'],'expected_version'=>['required','integer','min:1'],'reason'=>['required_if:action,cancel_admin','nullable','string','max:1000']])->validate();
        return DB::transaction(function () use ($admin,$id,$data) {
            $activity = Activity::whereKey($id)->lockForUpdate()->firstOrFail();
            abort_unless($activity->version === (int)$data['expected_version'] && in_array($activity->status,['pending_registrations','proceeding_pending_payments'],true),409,'Activity changed. Refresh before making a decision.');
            $details = $activity->details;
            $action = $data['action'];
            $sessions = TrialSchedule::sessions($activity);
            $session = isset($data['trial_session']) ? (int) $data['trial_session'] : null;
            abort_if($session !== null && ! isset($sessions[$session]), 422, 'Select a session from this activity.');
            abort_if($sessions !== [] && $session === null && $action !== 'cancel_admin', 422, 'Select a session for this decision.');
            $before = $session === null ? $activity->status : TrialSchedule::status($activity, $session);
            abort_unless(in_array($before, ['pending_registrations', 'proceeding_pending_payments'], true), 409, 'This trial is closed.');
            $count = app(ParentInterests::class)->activeInterests($activity, $session)->count();
            if ($action !== 'cancel_admin') {
                abort_unless($before === 'pending_registrations' && ($details['trial_type'] ?? '') === 'group',422,'This action requires a group trial collecting registrations.');
                $minimum = (int)($details['trial_min'] ?? 0);
                abort_unless($minimum >= 1 && $minimum <= 100,409,'Review the trial group minimum first.');
                if ($action === 'confirm_minimum') {
                    abort_if($session !== null && app(ParentInterests::class)->seatHolds($activity, $session)->exists(),
                        409, 'Wait for outstanding seat offers to be accepted, declined or expire before confirming.');
                    abort_unless($count >= $minimum,409,'The trial has not reached its minimum interest.');
                    abort_unless(User::whereKey($activity->provider_id)->where('is_active',true)->exists(),409,'The provider account is suspended.');
                    try { $start = TrialSchedule::startsAt($sessions[$session] ?? (($details['trial_date'] ?? '').' '.($details['trial_time'] ?? ''))); }
                    catch (\Throwable) { abort(409,'Review the trial schedule first.'); }
                    abort_unless($start->isFuture(),409,'The trial has already started.');
                } else {
                    abort_unless($count < $minimum && ($details['deadline'] ?? '9999-12-31') < CarbonImmutable::now('Australia/Sydney')->toDateString(),409,'Low-number cancellation requires the deadline to have passed below minimum.');
                }
            }
            $next = match($action) {'confirm_minimum'=>'proceeding_pending_payments','cancel_low_numbers'=>'cancelled_low_numbers',default=>'cancelled_by_admin'};
            $this->transition($activity, $admin, $session, $action, $before, $next, $data['reason'] ?? null);
            return $activity;
        },5);
    }

    public function reopenSessionBelowMinimum(Activity $activity, int $session, User $parent): void
    {
        // Called inside the withdrawal transaction with the activity row locked.
        if ($activity->status !== 'pending_registrations'
            || TrialSchedule::status($activity, $session) !== 'proceeding_pending_payments'
            || app(ParentInterests::class)->activeInterests($activity, $session)->count() >= (int) $activity->details['trial_min']) {
            return;
        }

        $this->transition($activity, $parent, $session, 'minimum_lost', 'proceeding_pending_payments',
            'pending_registrations', 'Interest fell below the required minimum.');
    }

    private function transition(Activity $activity, User $actor, ?int $session, string $action, string $before, string $next, ?string $reason): void
    {
        $changes = ['version' => $activity->version + 1];
        if ($session === null) {
            $changes += ['status' => $next, 'review_reason' => $reason];
        } else {
            $details = $activity->details;
            $details['trial_session_statuses'][$session] = $next;
            $changes['details'] = $details;
        }
        $activity->update($changes);
        $event = ActivityEvent::create(['activity_id' => $activity->id, 'trial_session' => $session,
            'actor_id' => $actor->id, 'action' => $action, 'from_status' => $before, 'to_status' => $next, 'reason' => $reason]);
        app(NotificationOutbox::class)->activity($event, $activity);
    }
}
