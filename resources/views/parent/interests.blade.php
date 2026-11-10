@extends('layouts.app')
@section('title', 'Your registrations · GoTry')
@section('content')
<div class="heading-row"><div><p class="eyebrow">Parent dashboard</p><h1>Hello, {{ auth()->user()->name }}</h1></div><a class="button" href="{{ route('activities.index') }}">Explore activities →</a></div>
<p>Manage your children's trial interest. Registering interest is free and does not confirm a place or request payment.</p>
@if(session('status'))<p class="notice" role="status">{{ session('status') }}</p>@endif
@if($errors->any())<div class="error" role="alert">{{ $errors->first() }}</div>@endif
<div class="grid">
@forelse($interests as $interest)
    @php($activity = $interest->activity)
    <article>
        <span class="status {{ $interest->status === 'interested' ? 'status-pending_registrations' : 'status-withdrawn' }}">{{ $interest->status === 'interested' ? 'Interest registered' : 'Withdrawn' }}</span>
        <h2>@if($activity->status === 'pending_registrations')<a href="{{ route('activities.show', $activity) }}">{{ $activity->title }}</a>@else{{ $activity->title }}@endif</h2>
        <p>{{ $interest->child_name }} · Age {{ $interest->child_age }}</p>
        <p>Activity status: {{ $activity->statusLabel() }}</p>
        @if(($activity->details['trial_type'] ?? '')==='group')<p>{{ $activity->details['trial_date'] }} · {{ $activity->details['trial_time'] }} (Sydney)</p>@endif
        @if($interest->status === 'interested')
            <form method="post" action="{{ route('parent.withdraw', $interest) }}">@csrf
                <input type="hidden" name="expected_version" value="{{ $interest->version }}">
                <label class="check-row"><input type="checkbox" name="withdraw_confirm" value="1" required> I want to withdraw this registration.</label>
                <button class="secondary" type="submit">Withdraw interest</button>
            </form>
        @elseif($availability[$activity->id]['open'])
            <form method="post" action="{{ route('parent.restore', $interest) }}">@csrf
                <input type="hidden" name="expected_version" value="{{ $interest->version }}">
                <input type="hidden" name="expected_activity_version" value="{{ $activity->version }}">
                <label class="check-row"><input type="checkbox" name="interest_terms" value="1" required> I have reviewed the current activity details and want to restore this interest.</label>
                <button type="submit">Restore interest</button>
            </form>
        @else<p>{{ $availability[$activity->id]['reason'] }}</p>@endif
    </article>
@empty<p>No registrations yet. Explore activities to find a trial.</p>@endforelse
</div>
{{ $interests->links() }}
@endsection
