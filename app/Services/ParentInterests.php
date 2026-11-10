<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Interest;
use App\Models\InterestEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ParentInterests
{
    public function availability(Activity $activity, ?int $count = null): array
    {
        $count ??= Interest::where('activity_id', $activity->id)->where('status', 'interested')->count();
        $reason = $this->closedReason($activity);
        $group = ($activity->details['trial_type'] ?? null) === 'group';
        $capacity = $group ? (int) ($activity->details['trial_max'] ?? 0) : null;
        $remaining = $capacity === null ? null : max(0, $capacity - $count);
        if ($reason === null && $remaining === 0) $reason = 'This trial is full. Please check again if a place becomes available.';
        return ['open' => $reason === null, 'reason' => $reason, 'count' => $count, 'capacity' => $capacity,
            'remaining' => $remaining, 'minimum_reached' => $group && $count >= (int) ($activity->details['trial_min'] ?? PHP_INT_MAX)];
    }

    private function closedReason(Activity $activity): ?string
    {
        if ($activity->status !== 'pending_registrations') return 'This activity is not accepting interest.';
        $data = $activity->details;
        $today = CarbonImmutable::now('Australia/Sydney');
        if (! isset($data['deadline']) || ! $this->validDate($data['deadline']) || $data['deadline'] < $today->toDateString()) return 'The registration deadline has passed.';
        if (($data['trial_type'] ?? null) === 'individual') return null;
        if (($data['trial_type'] ?? null) !== 'group' || ! $this->validDate($data['trial_date'] ?? '')
            || ! preg_match('/^\d{2}:\d{2}$/', $data['trial_time'] ?? '')
            || ! filter_var($data['trial_max'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]])) return 'The trial details need provider review.';
        try {
            $local = $data['trial_date'].' '.$data['trial_time'];
            $start = CarbonImmutable::createFromFormat('!Y-m-d H:i', $local, 'Australia/Sydney');
            if ($start->format('Y-m-d H:i') !== $local || $start->lessThanOrEqualTo($today)) return 'The trial has already started or its schedule has changed.';
        } catch (\Throwable) { return 'The trial schedule needs provider review.'; }
        return null;
    }

    private function validDate(mixed $date): bool
    {
        if (! is_string($date) || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts)) return false;
        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
    }

    private function assertParent(User $parent): void
    {
        abort_unless($parent->role === 'parent', 403);
    }

    private function lockActivity(int $activityId): Activity
    {
        // All writes take this same row lock first, including withdrawal and restoration.
        // On InnoDB this serializes capacity decisions across accounts and devices.
        return Activity::whereKey($activityId)->lockForUpdate()->firstOrFail();
    }

    private function assertAccepting(Activity $activity, int $expectedVersion, int $age): void
    {
        abort_unless($activity->status === 'pending_registrations', 404, 'Activity not available.');
        abort_unless($activity->version === $expectedVersion, 409, 'Activity details changed. Refresh and review them before registering.');
        $reason = $this->closedReason($activity);
        abort_if($reason !== null, 409, $reason ?? 'Registration closed.');
        $data = $activity->details;
        abort_unless($age >= (int) ($data['min_age'] ?? 19) && $age <= (int) ($data['max_age'] ?? 0), 422, 'The child is outside this activity’s age range.');
        $availability = $this->availability($activity);
        abort_unless($availability['open'], 409, $availability['reason'] ?? 'Trial unavailable.');
    }

    private function record(Interest $interest, User $actor, string $action, ?string $from): void
    {
        InterestEvent::create(['interest_id' => $interest->id, 'actor_id' => $actor->id, 'action' => $action,
            'from_status' => $from, 'to_status' => $interest->status, 'version' => $interest->version]);
    }

    public function register(User $parent, int $activityId, array $input): Interest
    {
        $this->assertParent($parent);
        if (is_string($input['child_name'] ?? null)) $input['child_name'] = Str::squish($input['child_name']);
        $data = Validator::make($input, ['child_name' => ['required','string','max:80'], 'child_age' => ['required','integer','between:1,18'],
            'expected_activity_version' => ['required','integer','min:1']])->validate();
        $key = hash('sha256', mb_strtolower($data['child_name']));
        return DB::transaction(function () use ($parent, $activityId, $data, $key) {
            $activity = $this->lockActivity($activityId);
            $existing = Interest::where('activity_id', $activity->id)->where('parent_id', $parent->id)->where('child_key', $key)->lockForUpdate()->first();
            if ($existing) {
                // Retried old create forms never resurrect a withdrawn registration.
                abort_unless($existing->status === 'interested', 409, 'This registration was withdrawn. Use Restore on your dashboard.');
                abort_unless($existing->child_age === (int) $data['child_age'], 409, 'An active registration already exists for this child with different details.');
                return $existing;
            }
            $this->assertAccepting($activity, (int) $data['expected_activity_version'], (int) $data['child_age']);
            $interest = Interest::create(['activity_id' => $activity->id, 'parent_id' => $parent->id, 'child_name' => $data['child_name'],
                'child_key' => $key, 'child_age' => $data['child_age'], 'status' => 'interested', 'version' => 1, 'activity_version' => $activity->version]);
            $this->record($interest, $parent, 'registered', null);
            return $interest;
        }, 5);
    }

    public function change(User $parent, int $interestId, string $action, array $input): Interest
    {
        $this->assertParent($parent);
        abort_unless(in_array($action, ['withdraw','restore'], true), 404);
        $data = Validator::make($input, ['expected_version' => ['required','integer','min:1'],
            'expected_activity_version' => [$action === 'restore' ? 'required' : 'nullable','integer','min:1']])->validate();
        $snapshot = Interest::whereKey($interestId)->where('parent_id', $parent->id)->firstOrFail();
        return DB::transaction(function () use ($parent, $snapshot, $action, $data) {
            $activity = $this->lockActivity($snapshot->activity_id);
            $interest = Interest::whereKey($snapshot->id)->where('parent_id', $parent->id)->lockForUpdate()->firstOrFail();
            $target = $action === 'withdraw' ? 'withdrawn' : 'interested';
            $expected = (int) $data['expected_version'];
            // An immediate retry is harmless; old forms from before a restore are rejected.
            if ($interest->status === $target && $interest->version === $expected + 1) return $interest;
            $source = $action === 'withdraw' ? 'interested' : 'withdrawn';
            abort_unless($interest->version === $expected && $interest->status === $source, 409, 'Registration changed. Refresh your dashboard and try again.');
            if ($action === 'restore') $this->assertAccepting($activity, (int) $data['expected_activity_version'], $interest->child_age);
            $from = $interest->status;
            $interest->update(['status' => $target, 'version' => $interest->version + 1,
                'activity_version' => $action === 'restore' ? $activity->version : $interest->activity_version,
                'withdrawn_at' => $action === 'withdraw' ? now() : null]);
            $this->record($interest, $parent, $action === 'withdraw' ? 'withdrawn' : 'restored', $from);
            return $interest;
        }, 5);
    }
}
