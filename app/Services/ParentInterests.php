<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Interest;
use App\Models\InterestEvent;
use App\Models\User;
use App\Models\ActivityEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ParentInterests
{
    public function availability(Activity $activity, ?int $count = null, ?int $session = null): array
    {
        $count ??= $this->activeInterests($activity, $session)->count();
        $reason = $this->closedReason($activity, $session);
        $acceptingOffers = $session !== null && $reason === null;
        $group = ($activity->details['trial_type'] ?? null) === 'group';
        $capacity = $group ? (int) ($activity->details['trial_max'] ?? 0) : null;
        $held = $session === null ? 0 : $this->seatHolds($activity, $session)->count();
        $remaining = $capacity === null ? null : max(0, $capacity - $count - $held);
        $waiting = $session !== null && $this->eligibleWaitlist($activity, $session)->exists();
        $canWaitlist = $session !== null && $reason === null && ($remaining === 0 || $waiting);
        if ($reason === null && $waiting && $remaining > 0) $reason = 'Available seats are offered to the waitlist first.';
        if ($reason === null && $remaining === 0) $reason = 'This trial is full. Please check again if a place becomes available.';
        return ['open' => $reason === null, 'reason' => $reason, 'count' => $count, 'capacity' => $capacity,
            'remaining' => $remaining, 'can_waitlist' => $canWaitlist, 'accepting_offers' => $acceptingOffers, 'held' => $held, 'minimum_reached' => $group && $count >= (int) ($activity->details['trial_min'] ?? PHP_INT_MAX)];
    }

    public function activeInterests(Activity $activity, ?int $session)
    {
        return $this->sessionInterests($activity, $session)->where('status', 'interested');
    }

    private function sessionInterests(Activity $activity, ?int $session)
    {
        return Interest::where('activity_id', $activity->id)->where('trial_session', $session);
    }

    private function eligibleWaitlist(Activity $activity, int $session)
    {
        return $this->sessionInterests($activity, $session)->where('status', 'waitlisted')
            ->whereIn('parent_id', User::where('role', 'parent')->where('is_active', true)->select('id'));
    }

    public function seatHolds(Activity $activity, int $session)
    {
        return $this->sessionInterests($activity, $session)->where('status', 'offered')->where('offer_expires_at', '>', now());
    }

    public function sessionSummary(Activity $activity): array
    {
        $sessions = TrialSchedule::sessions($activity);
        if ($sessions === []) return [];

        $counts = Interest::where('activity_id', $activity->id)->where('status', 'interested')
            ->whereNotNull('trial_session')->groupBy('trial_session')->selectRaw('trial_session, count(*) as total')
            ->pluck('total', 'trial_session');
        foreach ($sessions as $number => $start) {
            $sessions[$number] = [
                'start' => $start,
                'status' => $activity->status === 'pending_registrations' ? TrialSchedule::status($activity, $number) : $activity->status,
                'count' => (int) ($counts[$number] ?? 0),
                'can_offer' => $this->closedReason($activity, $number) === null
                    && ($counts[$number] ?? 0) + $this->seatHolds($activity, $number)->count() < (int) $activity->details['trial_max'],
                'waiting' => $this->eligibleWaitlist($activity, $number)->count(),
            ];
        }

        return $sessions;
    }

    private function closedReason(Activity $activity, ?int $session): ?string
    {
        if ($activity->status !== 'pending_registrations') return 'This activity is not accepting interest.';
        if (! User::whereKey($activity->provider_id)->where('is_active',true)->exists()) return 'The provider is currently unavailable.';
        $data = $activity->details;
        $today = CarbonImmutable::now('Australia/Sydney');
        if (! isset($data['deadline']) || ! $this->validDate($data['deadline']) || $data['deadline'] < $today->toDateString()) return 'The registration deadline has passed.';
        $sessions = TrialSchedule::sessions($activity);
        if ($sessions !== []) {
            if (! isset($sessions[$session])) return 'Select an available trial session.';
            $status = TrialSchedule::status($activity, $session);
            if ($status !== 'pending_registrations') return Activity::STATUSES[$status].'. This session is closed for registrations.';
            [$data['trial_date'], $data['trial_time']] = explode(' ', $sessions[$session]);
        } elseif ($session !== null) {
            return 'Select an available trial.';
        }
        if (($data['trial_type'] ?? null) === 'individual') return null;
        if (($data['trial_type'] ?? null) !== 'group' || ! $this->validDate($data['trial_date'] ?? '')
            || ! preg_match('/^\d{2}:\d{2}$/', $data['trial_time'] ?? '')
            || ! filter_var($data['trial_max'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]])) return 'The trial details need provider review.';
        try {
            $local = $data['trial_date'].' '.$data['trial_time'];
            $start = TrialSchedule::startsAt($local);
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
        abort_unless($parent->role === 'parent' && $parent->is_active, 403);
    }

    private function lockActivity(int $activityId): Activity
    {
        // All writes take this same row lock first, including withdrawal and restoration.
        // On InnoDB this serializes capacity decisions across accounts and devices.
        return Activity::whereKey($activityId)->lockForUpdate()->firstOrFail();
    }

    private function assertEligible(Activity $activity, int $expectedVersion, int $age, ?int $session): void
    {
        abort_unless($activity->status === 'pending_registrations', 404, 'Activity not available.');
        abort_unless($activity->version === $expectedVersion, 409, 'Activity details changed. Review the latest trial details before submitting again.');
        $reason = $this->closedReason($activity, $session);
        abort_if($reason !== null, 409, $reason ?? 'Registration closed.');
        $data = $activity->details;
        abort_unless($age >= (int) ($data['min_age'] ?? 19) && $age <= (int) ($data['max_age'] ?? 0), 422, 'The child is outside this activity’s age range.');
    }

    private function assertAccepting(Activity $activity, int $expectedVersion, int $age, ?int $session): void
    {
        $this->assertEligible($activity, $expectedVersion, $age, $session);
        $availability = $this->availability($activity, session: $session);
        abort_unless($availability['open'], 409, $availability['reason'] ?? 'Trial unavailable.');
    }

    private function record(Interest $interest, ?User $actor, string $action, ?string $from, Activity $activity, ?array $scheduleChange = null): void
    {
        $event = InterestEvent::create(['interest_id' => $interest->id, 'actor_id' => $actor?->id, 'action' => $action,
            'from_status' => $from, 'to_status' => $interest->status, 'version' => $interest->version, 'schedule_change' => $scheduleChange, 'offer_expires_at' => $interest->offer_expires_at]);
        app(NotificationOutbox::class)->interest($event, $interest, $activity);
    }

    public function register(User $parent, int $activityId, array $input, bool $waitlist = false): Interest
    {
        $this->assertParent($parent);
        if (is_string($input['child_name'] ?? null)) $input['child_name'] = Str::squish($input['child_name']);
        $data = Validator::make($input, ['child_name' => ['required','string','max:80'], 'child_age' => ['required','integer','between:1,18'],
            'expected_activity_version' => ['required','integer','min:1'], 'trial_session' => ['nullable','integer','between:1,10']])->validate();
        $key = hash('sha256', mb_strtolower($data['child_name']));
        return DB::transaction(function () use ($parent, $activityId, $data, $key, $waitlist) {
            $activity = $this->lockActivity($activityId);
            $session = isset($data['trial_session']) ? (int) $data['trial_session'] : null;
            $existing = Interest::where('activity_id', $activity->id)->where('parent_id', $parent->id)->where('child_key', $key)->lockForUpdate()->first();
            if ($existing) {
                // Retried old create forms never resurrect a withdrawn registration.
                abort_unless($waitlist ? in_array($existing->status, ['waitlisted', 'offered'], true) : $existing->status === 'interested',
                    409, 'This child already has an entry. Manage it from your dashboard.');
                abort_unless($existing->child_age === (int) $data['child_age'], 409, 'An active registration already exists for this child with different details.');
                abort_unless($existing->trial_session === $session, 409, 'This child already has a registration for another session.');
                return $existing;
            }
            if ($waitlist) {
                $this->assertEligible($activity, (int) $data['expected_activity_version'], (int) $data['child_age'], $session);
                abort_unless($this->availability($activity, session: $session)['can_waitlist'], 409, 'This session is no longer taking waitlist entries. Review the current options before submitting again.');
            } else {
                $this->assertAccepting($activity, (int) $data['expected_activity_version'], (int) $data['child_age'], $session);
            }
            $interest = Interest::create(['activity_id' => $activity->id, 'trial_session' => $session, 'parent_id' => $parent->id, 'child_name' => $data['child_name'],
                'child_key' => $key, 'child_age' => $data['child_age'], 'status' => $waitlist ? 'waitlisted' : 'interested', 'version' => 1, 'activity_version' => $activity->version]);
            $this->record($interest, $parent, $waitlist ? 'waitlisted' : 'registered', null, $activity);
            return $interest;
        }, 5);
    }

    public function offerNext(User $provider, int $activityId, int $session, int $version): void
    {
        abort_unless($provider->role === 'provider' && $provider->is_active, 403);
        DB::transaction(function () use ($provider, $activityId, $session, $version): void {
            $activity = $this->lockActivity($activityId);
            abort_unless($activity->provider_id === $provider->id, 404);
            abort_unless($activity->version === $version && $this->closedReason($activity, $session) === null,
                409, 'This session is closed or has changed.');
            $capacity = $this->availability($activity, session: $session);
            abort_unless($capacity['remaining'] > 0, 409, 'There are no unreserved seats.');
            $next = $this->eligibleWaitlist($activity, $session)->oldest('id')->lockForUpdate()->first();
            abort_if($next === null, 409, 'There are no eligible families waiting for this session.');
            // A duplicate provider form must not offer the next seat.
            $activity->increment('version');
            $this->expireOffers($activity, $session, $provider);
            $this->offerSeat($activity, $next, $provider);
        }, 5);
    }

    public function processWaitlist(int $activityId): void
    {
        DB::transaction(function () use ($activityId): void {
            $activity = $this->lockActivity($activityId);
            $versionBumped = false;

            foreach (array_keys(TrialSchedule::sessions($activity)) as $session) {
                $this->expireOffers($activity, $session, null);
                if ($this->closedReason($activity, $session) !== null) continue;

                $remaining = $this->availability($activity, session: $session)['remaining'];
                if ($remaining <= 0) continue;

                $entries = $this->eligibleWaitlist($activity, $session)->oldest('id')
                    ->limit($remaining)->lockForUpdate()->get();
                if ($entries->isEmpty()) continue;

                if (! $versionBumped) {
                    $activity->increment('version');
                    $versionBumped = true;
                }
                foreach ($entries as $entry) $this->offerSeat($activity, $entry, null);
            }
        }, 5);
    }

    private function expireOffers(Activity $activity, int $session, ?User $actor): void
    {
        $expired = $this->sessionInterests($activity, $session)->where('status', 'offered')
            ->where('offer_expires_at', '<=', now())->lockForUpdate()->get();
        foreach ($expired as $entry) $this->updateWaitlist($entry, $actor, $activity, 'offer_expired', 'withdrawn');
    }

    private function offerSeat(Activity $activity, Interest $entry, ?User $actor): void
    {
        $expires = min(CarbonImmutable::now()->addHours(24),
            TrialSchedule::startsAt(TrialSchedule::sessions($activity)[$entry->trial_session]),
            CarbonImmutable::parse($activity->details['deadline'], 'Australia/Sydney')->addDay()->startOfDay(),
        );
        $this->updateWaitlist($entry, $actor, $activity, 'offered', 'offered', $expires->setTimezone(config('app.timezone')));
    }

    public function respondToWaitlist(User $parent, int $interestId, array $data): void
    {
        $this->assertParent($parent);
        $snapshot = Interest::whereKey($interestId)->where('parent_id', $parent->id)->firstOrFail();
        DB::transaction(function () use ($parent, $snapshot, $data): void {
            $activity = $this->lockActivity($snapshot->activity_id);
            $interest = Interest::whereKey($snapshot->id)->where('parent_id', $parent->id)->lockForUpdate()->firstOrFail();
            abort_unless($interest->version === (int) $data['expected_version'] && in_array($interest->status, ['waitlisted', 'offered'], true),
                409, 'The waitlist entry changed.');
            if ($data['decision'] === 'leave') {
                $expired = $interest->status === 'offered' && ! $interest->offer_expires_at->isFuture();
                $this->updateWaitlist($interest, $parent, $activity, $expired ? 'offer_expired' : 'waitlist_left', 'withdrawn');
                return;
            }

            abort_unless($interest->status === 'offered' && $interest->offer_expires_at->isFuture(), 409, 'This seat offer has expired or is unavailable.');
            $this->assertEligible($activity, (int) $data['expected_activity_version'], $interest->child_age, $interest->trial_session);
            $availability = $this->availability($activity, session: $interest->trial_session);
            abort_unless($availability['count'] + $availability['held'] <= $availability['capacity'], 409, 'This session has no available capacity.');
            $this->updateWaitlist($interest, $parent, $activity, 'offer_accepted', 'interested');
        }, 5);
    }

    private function updateWaitlist(Interest $interest, ?User $actor, Activity $activity, string $action, string $status, ?CarbonImmutable $expires = null): void
    {
        $previous = $interest->status;
        $interest->update(['status' => $status, 'version' => $interest->version + 1, 'activity_version' => $activity->version,
            'offer_expires_at' => $expires ?? $interest->offer_expires_at, 'withdrawn_at' => $status === 'withdrawn' ? now() : null]);
        $this->record($interest, $actor, $action, $previous, $activity);
    }

    public function transfer(User $parent, int $interestId, array $input): Interest
    {
        $this->assertParent($parent);
        $snapshot = Interest::whereKey($interestId)->where('parent_id', $parent->id)->firstOrFail();
        $data = Validator::make($input, [
            'expected_version' => ['required', 'integer', 'min:1'],
            'expected_activity_version' => ['required', 'integer', 'min:1'],
            'trial_session' => ['required', 'integer', 'between:1,10'],
        ])->validate();

        return DB::transaction(function () use ($parent, $snapshot, $data) {
            $activity = $this->lockActivity($snapshot->activity_id);
            $interest = Interest::whereKey($snapshot->id)->where('parent_id', $parent->id)->lockForUpdate()->firstOrFail();
            abort_unless($interest->status === 'interested' && $interest->version === (int) $data['expected_version'],
                409, 'Registration changed. Refresh your dashboard and try again.');
            $source = $interest->trial_session;
            $destination = (int) $data['trial_session'];
            $sessions = TrialSchedule::sessions($activity);
            abort_unless(isset($sessions[$source], $sessions[$destination]) && $source !== $destination,
                422, 'Choose a different session from this activity.');
            abort_unless(TrialSchedule::startsAt($sessions[$source])->isFuture(), 409, 'The original session has already started.');
            $this->assertAccepting($activity, (int) $data['expected_activity_version'], $interest->child_age, $destination);

            $interest->update(['trial_session' => $destination, 'version' => $interest->version + 1,
                'activity_version' => $activity->version]);
            $this->record($interest, $parent, 'transferred', 'interested', $activity, [
                'from_session' => $source, 'to_session' => $destination,
                'from_start' => $sessions[$source], 'to_start' => $sessions[$destination],
            ]);
            app(AdminActivityLifecycle::class)->reopenSessionBelowMinimum($activity, $source, $parent);
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
            $eventAction = $action === 'withdraw' ? 'withdrawn' : 'restored';
            // A transfer also increments the version, so verify the action on a retry.
            if ($interest->status === $target && $interest->version === $expected + 1
                && InterestEvent::where('interest_id', $interest->id)->where('version', $interest->version)
                    ->where('action', $eventAction)->exists()) return $interest;
            $source = $action === 'withdraw' ? 'interested' : 'withdrawn';
            abort_unless($interest->version === $expected && $interest->status === $source, 409, 'Registration changed. Refresh your dashboard and try again.');
            if ($action === 'restore') $this->assertAccepting($activity, (int) $data['expected_activity_version'], $interest->child_age, $interest->trial_session);
            $from = $interest->status;
            $interest->update(['status' => $target, 'version' => $interest->version + 1,
                'activity_version' => $action === 'restore' ? $activity->version : $interest->activity_version,
                'withdrawn_at' => $action === 'withdraw' ? now() : null]);
            $this->record($interest, $parent, $eventAction, $from, $activity);
            if ($action === 'withdraw' && $interest->trial_session !== null) {
                app(AdminActivityLifecycle::class)->reopenSessionBelowMinimum($activity, $interest->trial_session, $parent);
            }
            if ($action === 'withdraw' && $interest->trial_session === null && $activity->status === 'proceeding_pending_payments' && ($activity->details['trial_type'] ?? '') === 'group'
                && Interest::where('activity_id',$activity->id)->where('status','interested')->count() < (int)$activity->details['trial_min']) {
                $activity->update(['status'=>'pending_registrations','version'=>$activity->version+1,'review_reason'=>'Interest fell below the required minimum; admin review is needed.']);
                $event = ActivityEvent::create(['activity_id'=>$activity->id,'actor_id'=>$parent->id,'action'=>'minimum_lost',
                    'from_status'=>'proceeding_pending_payments','to_status'=>'pending_registrations','reason'=>'Interest fell below the required minimum.']);
                app(NotificationOutbox::class)->activity($event,$activity);
            }
            return $interest;
        }, 5);
    }
}
