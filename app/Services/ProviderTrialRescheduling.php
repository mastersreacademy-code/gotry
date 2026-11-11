<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ActivityEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProviderTrialRescheduling
{
    public function reschedule(User $provider, int $activityId, array $input): void
    {
        abort_unless($provider->role === 'provider' && $provider->is_active, 403);
        $data = Validator::make($input, [
            'expected_activity_version' => ['required', 'integer', 'min:1'],
            'trial_session' => ['required', 'integer', 'between:1,10'],
            'trial_date' => ['required', 'date_format:Y-m-d'],
            'trial_time' => ['required', 'date_format:H:i'],
        ])->validate();

        DB::transaction(function () use ($provider, $activityId, $data): void {
            $activity = Activity::whereKey($activityId)->where('provider_id', $provider->id)->lockForUpdate()->firstOrFail();
            abort_unless($activity->status === 'pending_registrations' && $activity->version === (int) $data['expected_activity_version'],
                409, 'Activity changed or is closed. Refresh before rescheduling.');
            $sessions = TrialSchedule::sessions($activity);
            $number = (int) $data['trial_session'];
            abort_unless(isset($sessions[$number]), 422, 'Choose a group trial session from this activity.');
            $status = TrialSchedule::status($activity, $number);
            $previous = $sessions[$number];
            abort_unless($status === 'pending_registrations' && TrialSchedule::startsAt($previous)->isFuture(),
                409, 'Only future sessions collecting registrations can be rescheduled.');

            $newStart = $data['trial_date'].' '.$data['trial_time'];
            try {
                $newTime = TrialSchedule::startsAt($newStart);
            } catch (\Exception) {
                throw ValidationException::withMessages(['trial_time' => 'Choose a valid Sydney time outside daylight-saving transitions.']);
            }
            if (! $newTime->isFuture() || $data['trial_date'] < $activity->details['deadline'] || in_array($newStart, $sessions, true)) {
                throw ValidationException::withMessages(['trial_time' => 'Choose a different future start on or after the registration deadline.']);
            }

            $sessions[$number] = $newStart;
            $details = $activity->details;
            [$details['trial_date'], $details['trial_time']] = explode(' ', $sessions[1]);
            $details['extra_trial_times'] = implode("\n", array_slice($sessions, 1));
            $activity->update(['details' => $details, 'version' => $activity->version + 1]);
            $event = ActivityEvent::create([
                'activity_id' => $activity->id, 'actor_id' => $provider->id, 'trial_session' => $number,
                'action' => 'rescheduled', 'from_status' => $status, 'to_status' => $status,
                'schedule_change' => ['from_start' => $previous, 'to_start' => $newStart],
            ]);
            app(NotificationOutbox::class)->activity($event, $activity);
        }, 5);
    }
}
