@extends('layouts.app')
@section('title', $activity->title.' · GoTry')
@section('content')
<section class="panel">
    <a href="{{ route('provider.dashboard') }}">← Your activities</a>
    <h1>{{ $activity->title }}</h1><span class="status status-{{ $activity->status }}">{{ $activity->statusLabel() }}</span>
    @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
    @if($activity->review_reason)<p class="notice"><strong>Admin feedback:</strong> {{ $activity->review_reason }}</p>@endif
    @include('provider.partials.review')
    @include('provider.partials.prices')
    @include('provider.partials.trial-sessions')
    @if($errors->any())<p class="error" role="alert">{{ $errors->first() }}</p>@endif
    @php($reschedulable = array_filter($sessions, fn ($session) => $session['status'] === 'pending_registrations' && \App\Services\TrialSchedule::startsAt($session['start'])->isFuture()))
    @if($reschedulable !== [])
    <form method="post" action="{{ route('provider.reschedule', $activity) }}">@csrf
        <h2>Reschedule a trial session</h2>
        <p>Existing interest stays with the session. Registered parents receive an in-app notice. The registration deadline stays the same.</p>
        <input type="hidden" name="expected_activity_version" value="{{ $activity->version }}">
        <label for="trial_session">Current session (Sydney time)</label>
        <select id="trial_session" name="trial_session" required>
            <option value="">Choose a session</option>
            @foreach($reschedulable as $number => $session)
            <option value="{{ $number }}" @selected((string) old('trial_session') === (string) $number)>{{ $session['start'] }}</option>
            @endforeach
        </select>
        <div class="fields">
            <div><label for="trial_date">New date</label><input id="trial_date" type="date" name="trial_date" value="{{ old('trial_date') }}" required></div>
            <div><label for="trial_time">New start (Sydney time)</label><input id="trial_time" type="time" name="trial_time" value="{{ old('trial_time') }}" required></div>
        </div>
        <label class="check-row"><input type="checkbox" name="reschedule_confirm" value="1" required> I confirm the new start and want to notify affected parents.</label>
        <button type="submit">Reschedule trial</button>
    </form>
    @endif
</section>
@endsection
