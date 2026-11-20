@extends('layouts.app')
@section('title', 'My List · GoTry')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/parent.css') }}">
@endpush
@section('content')
<div class="parent-ui parent-dashboard">
<section class="parent-banner portal-banner">
    <div>
        <h1>My List</h1>
        <p>Your children’s next experiences, all in one place.</p>
    </div>
    <div class="portal-actions">
        <a class="portal-bell" href="{{ route('notifications.index') }}" aria-label="Notifications, {{ $unread }} unread" title="Notifications">
            @include('layouts.navigation-icon', ['icon' => 'bell'])
            @if($unread > 0)
                <span class="portal-bell-badge" aria-hidden="true">{{ $unread > 99 ? '99+' : $unread }}</span>
            @endif
        </a><a class="button" href="{{ route('activities.index') }}">Explore activities →</a><a class="button secondary" href="{{ route('parent.children') }}">My children · Add a child</a></div>
</section>
<nav class="portal-navigation" aria-label="My List sections">
    <a href="#registered-interest">Registered Interest</a>
    <a href="#ready-for-payment">Ready for Payment</a>
    <a href="#confirmed">Confirmed</a>
    <a href="#past-registrations">Past registrations</a>
</nav>
<h2 class="parent-list-title">{{ $activityFilter ? 'Registrations for this activity' : 'Your registrations' }}</h2>
@if($activityFilter)
    <p><a href="{{ route('parent.dashboard') }}">← All registrations</a></p>
    @if($interests->isEmpty())<p>No registrations for this activity</p>@endif
@endif
@if(session('status'))<p class="notice" role="status">{{ session('status') }}</p>@endif
@if($errors->any())<div class="error" role="alert">{{ $errors->first() }}</div>@endif
@php
    $groups = $interests->getCollection()->groupBy(function ($interest) use ($payments) {
        $activity = $interest->activity;
        $status = $activity->status === 'pending_registrations'
            ? \App\Services\TrialSchedule::status($activity, $interest->trial_session)
            : $activity->status;
        if ($interest->status === 'withdrawn' || str_starts_with($status, 'cancelled_')) return 'Past registrations';
        if ($payments->get($interest->id)?->paid_at) return 'Confirmed';
        return $interest->status === 'interested' && $status === 'proceeding_pending_payments'
            ? 'Ready for Payment' : 'Registered Interest';
    });
@endphp
@foreach(['Registered Interest', 'Ready for Payment', 'Confirmed', 'Past registrations'] as $group)
<section class="portal-group" id="{{ \Illuminate\Support\Str::slug($group) }}">
<div class="portal-group-heading"><h3>{{ $group }}</h3><span>{{ $groups->get($group, collect())->count() }} on this page</span></div>
<div class="grid portal-registration-grid">
@forelse($groups->get($group, []) as $interest)
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
        <div class="portal-child"><span class="portal-child-avatar" aria-hidden="true">{{ mb_substr($interest->child_name, 0, 1) }}</span><div><strong>{{ $interest->child_name }}</strong><span>Age {{ $interest->child_age }}</span></div></div>
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
            <summary>Remove from My List</summary>
            <form method="post" action="{{ route('parent.withdraw', $interest) }}">@csrf
                <input type="hidden" name="return_to_dashboard" value="1">
                <input type="hidden" name="expected_version" value="{{ $interest->version }}">
                <label class="check-row"><input type="checkbox" name="withdraw_confirm" value="1" required> I want to withdraw this registration.</label>
                @if($payment?->paid_at)<p>The Sandbox payment stays in your history.</p>@endif
                <button class="secondary" type="submit">Confirm removal</button>
            </form>
            </details>
            @if(! $payment?->paid_at && $sessionStart && $destinations !== [] && \App\Services\TrialSchedule::startsAt($sessionStart)->isFuture())
            <details class="parent-manage">
            <summary>Move to another session</summary>
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
            </details>
            @endif
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
        <p>{{ match($group) {
            'Ready for Payment' => 'Trials ready for payment will appear here once the minimum interest is reached.',
            'Confirmed' => 'Your paid trial registrations will appear here.',
            default => 'No entries in this category',
        } }}</p>
    </article>
@endforelse
</div>
</section>
@endforeach
{{ $interests->links() }}
</div>
<script type="module" src="{{ asset('js/parent-tabs.js') }}"></script>
@endsection
