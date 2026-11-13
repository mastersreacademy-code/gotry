@extends('layouts.app')
@section('title', 'Your registrations · GoTry')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/parent.css') }}">
@endpush
@section('content')
<div class="parent-ui">
<section class="parent-banner">
    <div>
        <p class="eyebrow">Parent dashboard</p>
        <h1>Hello, {{ auth()->user()->name }}</h1>
        <p>Manage your children’s trial registrations, waitlists and seat offers.</p>
    </div>
    <a class="button" href="{{ route('activities.index') }}">Explore activities →</a>
</section>
<h2 class="parent-list-title">{{ $activityFilter ? 'Registrations for this activity' : 'Your registrations' }}</h2>
@if($activityFilter)
    <p><a href="{{ route('parent.dashboard') }}">← All registrations</a></p>
@endif
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
        $cancelled = $interest->status !== 'withdrawn' && in_array($sessionStatus, ['cancelled_by_admin', 'cancelled_low_numbers'], true);
        $liveOffer = $interest->status === 'offered' && $interest->offer_expires_at->isFuture();
        $canAccept = $liveOffer && $slot['accepting_offers'];
    @endphp
    <article @class(['parent-card', 'parent-card-offer' => $canAccept])>
        <span class="status {{ ($cancelled || ($interest->status === 'offered' && ! $canAccept)) ? 'parent-status-inactive' : 'parent-status-'.$interest->status }}">{{ $cancelled ? 'Trial cancelled' : match($interest->status) {
            'interested' => 'Interest registered', 'waitlisted' => 'On waitlist',
            'offered' => $canAccept ? 'Seat offered' : ($liveOffer ? 'Offer unavailable' : 'Offer expired'),
            'withdrawn' => match ($interest->latestEvent?->action) {
                'offer_expired' => 'Offer expired',
                'waitlist_left' => $interest->latestEvent->from_status === 'offered' ? 'Offer declined' : 'Left waitlist',
                default => 'Withdrawn',
            },
            default => 'Withdrawn',
        } }}</span>
        <h3>@if($activity->status === 'pending_registrations')<a href="{{ route('activities.show', $activity) }}">{{ $activity->title }}</a>@else{{ $activity->title }}@endif</h3>
        <p>{{ $interest->child_name }} · Age {{ $interest->child_age }}</p>
        @if($sessionStatus === 'pending_registrations' && !$activity->provider?->is_active)
            <p class="notice">The provider is currently unavailable. New registrations and seat-offer acceptance are paused.</p>
        @endif

        @if($sessionStart)
            <p>{{ $sessionStart }} (Sydney) · {{ \App\Models\Activity::STATUSES[$sessionStatus] }}</p>
        @elseif(($activity->details['trial_type'] ?? '')==='group')<p>{{ $activity->details['trial_date'] }} · {{ $activity->details['trial_time'] }} (Sydney) · {{ $activity->statusLabel() }}</p>@else<p>{{ $activity->statusLabel() }}</p>@endif
        @php($payment = $payments->get($interest->id))
        @if($payment?->paid_at)
            <p class="notice">Paid · AUD 2.00 · PayPal Sandbox</p>
        @elseif($interest->status === 'interested' && $sessionStatus === 'proceeding_pending_payments' && $activity->provider?->is_active && config('paypal.client_id') && config('paypal.client_secret'))
            @if($payment && session('payment_approval') === $payment->attempt)
                <form method="post" action="{{ route('parent.payment.capture', $interest) }}">@csrf
                    <input type="hidden" name="attempt" value="{{ $payment->attempt }}">
                    <button type="submit">Confirm AUD 2.00 Sandbox payment</button>
                </form>
            @else
                <form method="post" action="{{ route('parent.payment.start', $interest) }}">@csrf
                    <button type="submit">Pay AUD 2.00 with PayPal Sandbox</button>
                </form>
            @endif
        @endif
        @if($interest->status === 'interested')
            <details class="parent-manage" @if($errors->any()) open @endif>
            <summary>Manage registration</summary>
            <form method="post" action="{{ route('parent.withdraw', $interest) }}">@csrf
                <input type="hidden" name="expected_version" value="{{ $interest->version }}">
                <label class="check-row"><input type="checkbox" name="withdraw_confirm" value="1" required> I want to withdraw this registration.</label>
                <button class="secondary" type="submit">Withdraw interest</button>
            </form>
            @if($sessionStart && $destinations !== [] && \App\Services\TrialSchedule::startsAt($sessionStart)->isFuture())
            <form method="post" action="{{ route('parent.transfer', $interest) }}">@csrf
                <input type="hidden" name="return_to_dashboard" value="1">
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
            </details>
        @elseif(in_array($interest->status, ['waitlisted', 'offered'], true))
            <form method="post" action="{{ route('parent.waitlist', $interest) }}">@csrf
                <input type="hidden" name="return_to_dashboard" value="1">
                <input type="hidden" name="expected_version" value="{{ $interest->version }}">
                <input type="hidden" name="expected_activity_version" value="{{ $activity->version }}">
                @if($canAccept)
                    <p class="parent-deadline">Accept by <strong>{{ $interest->offer_expires_at->copy()->setTimezone('Australia/Sydney')->format('Y-m-d H:i T') }}</strong></p>
                    <button type="submit" name="decision" value="accept">Accept seat offer</button>
                @endif
                <button class="secondary" type="submit" name="decision" value="leave">{{ $canAccept ? 'Decline offer' : ($interest->status === 'offered' ? 'Close entry' : 'Leave waitlist') }}</button>
            </form>
        @elseif($slot['open'])
            <form method="post" action="{{ route('parent.restore', $interest) }}">@csrf
                <input type="hidden" name="return_to_dashboard" value="1">
                <input type="hidden" name="expected_version" value="{{ $interest->version }}">
                <input type="hidden" name="expected_activity_version" value="{{ $activity->version }}">
                <label class="check-row"><input type="checkbox" name="interest_terms" value="1" required> I have reviewed the current activity details and want to restore this interest.</label>
                <button type="submit">Restore interest</button>
            </form>
        @else<p>{{ $slot['reason'] }}</p>@endif
    </article>
@empty
    <article class="parent-empty">
        <h3>{{ $activityFilter ? 'No registrations for this activity' : 'No registrations yet' }}</h3>
        <p>Explore activities to find a trial. Registering interest is free and does not confirm a place.</p>
    </article>
@endforelse
</div>
{{ $interests->links() }}
</div>
@endsection
