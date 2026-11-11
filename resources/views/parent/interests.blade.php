@extends('layouts.app')
@section('title', 'Your registrations · GoTry')
@section('content')
<div class="heading-row"><div><p class="eyebrow">Parent dashboard</p><h1>Hello, {{ auth()->user()->name }}</h1></div><a class="button" href="{{ route('activities.index') }}">Explore activities →</a></div>
<p>Manage your children's trial interest. Registering interest is free and does not confirm a place or request payment.</p>
@if(session('status'))<p class="notice" role="status">{{ session('status') }}</p>@endif
@if($errors->any())<div class="error" role="alert">{{ $errors->first() }}</div>@endif
<div class="grid">
@forelse($interests as $interest)
    @php
        $activity = $interest->activity;
        $sessionStart = \App\Services\TrialSchedule::sessions($activity)[$interest->trial_session] ?? null;
        $sessionStatus = $activity->status === 'pending_registrations'
            ? \App\Services\TrialSchedule::status($activity, $interest->trial_session)
            : $activity->status;
        $destinations = $openSessions[$activity->id] ?? [];
        unset($destinations[$interest->trial_session]);
        $slot = $availability[$activity->id][$interest->trial_session ?? 0];
    @endphp
    <article>
        <span class="status {{ $interest->status === 'interested' ? 'status-pending_registrations' : 'status-withdrawn' }}">{{ match($interest->status) {
            'interested' => 'Interest registered', 'waitlisted' => 'On waitlist',
            'offered' => $interest->offer_expires_at->isFuture() ? 'Seat offered' : 'Offer expired',
            default => 'Withdrawn',
        } }}</span>
        <h2>@if($activity->status === 'pending_registrations')<a href="{{ route('activities.show', $activity) }}">{{ $activity->title }}</a>@else{{ $activity->title }}@endif</h2>
        <p>{{ $interest->child_name }} · Age {{ $interest->child_age }}</p>
        <p>Activity status: {{ $activity->statusLabel() }}</p>
        @if($sessionStart)
            <p>{{ $sessionStart }} (Sydney) · {{ \App\Models\Activity::STATUSES[$sessionStatus] }}</p>
        @elseif(($activity->details['trial_type'] ?? '')==='group')<p>{{ $activity->details['trial_date'] }} · {{ $activity->details['trial_time'] }} (Sydney)</p>@endif
        @if($interest->status === 'interested')
            <form method="post" action="{{ route('parent.withdraw', $interest) }}">@csrf
                <input type="hidden" name="expected_version" value="{{ $interest->version }}">
                <label class="check-row"><input type="checkbox" name="withdraw_confirm" value="1" required> I want to withdraw this registration.</label>
                <button class="secondary" type="submit">Withdraw interest</button>
            </form>
            @if($sessionStart && $destinations !== [] && \App\Services\TrialSchedule::startsAt($sessionStart)->isFuture())
            <form method="post" action="{{ route('parent.transfer', $interest) }}">@csrf
                <input type="hidden" name="expected_version" value="{{ $interest->version }}">
                <input type="hidden" name="expected_activity_version" value="{{ $activity->version }}">
                <label for="transfer-{{ $interest->id }}">Move to another trial session (Sydney time)</label>
                <select id="transfer-{{ $interest->id }}" name="trial_session" required>
                    <option value="">Choose a session</option>
                    @foreach($destinations as $number => $start)
                    <option value="{{ $number }}">{{ $start }}</option>
                    @endforeach
                </select>
                <label class="check-row"><input type="checkbox" name="transfer_confirm" value="1" required> Move this interest and release the original seat.</label>
                <button type="submit">Move interest</button>
            </form>
            @endif
        @elseif(in_array($interest->status, ['waitlisted', 'offered'], true))
            <form method="post" action="{{ route('parent.waitlist', $interest) }}">@csrf
                <input type="hidden" name="expected_version" value="{{ $interest->version }}">
                <input type="hidden" name="expected_activity_version" value="{{ $activity->version }}">
                @if($interest->status === 'offered' && $interest->offer_expires_at->isFuture() && $slot['accepting_offers'])
                    <p>Accept by {{ $interest->offer_expires_at->copy()->setTimezone('Australia/Sydney')->format('Y-m-d H:i T') }}.</p>
                    <button type="submit" name="decision" value="accept">Accept seat offer</button>
                @endif
                <button class="secondary" type="submit" name="decision" value="leave">Leave waitlist</button>
            </form>
        @elseif($slot['open'])
            <form method="post" action="{{ route('parent.restore', $interest) }}">@csrf
                <input type="hidden" name="expected_version" value="{{ $interest->version }}">
                <input type="hidden" name="expected_activity_version" value="{{ $activity->version }}">
                <label class="check-row"><input type="checkbox" name="interest_terms" value="1" required> I have reviewed the current activity details and want to restore this interest.</label>
                <button type="submit">Restore interest</button>
            </form>
        @else<p>{{ $slot['reason'] }}</p>@endif
    </article>
@empty<p>No registrations yet. Explore activities to find a trial.</p>@endforelse
</div>
{{ $interests->links() }}
@endsection
