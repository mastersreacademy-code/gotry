<?php

namespace App\Services;

use App\Models\Activity;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class TrialSchedule
{
    public static function startsAt(string $local): CarbonImmutable
    {
        $start = CarbonImmutable::createFromFormat('!Y-m-d H:i', $local, 'Australia/Sydney');
        if ($start->format('Y-m-d H:i') !== $local
            || $start->subHour()->format('Y-m-d H:i') === $local
            || $start->addHour()->format('Y-m-d H:i') === $local) {
            throw new InvalidArgumentException('Invalid or ambiguous Sydney trial time.');
        }

        return $start;
    }

    public static function sessions(Activity $activity): array
    {
        $details = $activity->details;
        if (($details['trial_type'] ?? null) !== 'group' || empty($details['extra_trial_times'])) return [];

        $times = [$details['trial_date'].' '.$details['trial_time'], ...preg_split('/\R/', $details['extra_trial_times'])];
        $sessions = [];
        foreach ($times as $index => $time) {
            $sessions[$index + 1] = $time;
        }

        return $sessions;
    }

    public static function status(Activity $activity, ?int $session): string
    {
        return $activity->details['trial_session_statuses'][$session] ?? 'pending_registrations';
    }
}
