@extends('layouts.app')
@section('title', $title.' · GoTry')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/discovery.css') }}">
@endpush
@section('content')
<div class="discovery-ui">
    <header class="discovery-detail-heading">
        <a href="{{ route('activities.index') }}">← Explore activities</a>
        <p class="eyebrow">{{ $category }}</p>
        <h1>{{ $title }}</h1>
        <p>Ages {{ $details['min_age'] }}–{{ $details['max_age'] }} · {{ $details['venue'] }}</p>
    </header>
    <div class="discovery-detail">
    <section class="panel discovery-trial" id="trial">
        <p class="eyebrow">Free introductory trial</p>
        <h2>Register interest for the trial</h2>
        <p>{{ $details['trial_duration'] }} minutes · Register interest by {{ $details['deadline'] }}</p>
        @if($sessions === [] && $details['trial_type'] === 'group')
            <p>{{ $details['trial_date'] }} at {{ $details['trial_time'] }} · Sydney</p>
        @elseif($details['trial_type'] === 'individual')
            <p class="preserve-lines">Ask provider about availability: {{ $details['trial_availability'] }}</p>
        @endif
        @php($openSessions = array_filter($sessions, fn ($session) => $session['open'] || $session['can_waitlist']))
        @if($sessions !== [])
            <p>Each session needs at least {{ $details['trial_min'] }} participants.</p>
            <ul class="discovery-sessions">
            @foreach($sessions as $session)
                <li><strong>{{ $session['start'] }} · Sydney</strong>
                    <span @class(['discovery-badge', 'discovery-badge-open' => $session['open'], 'discovery-badge-wait' => $session['can_waitlist']])>
                        {{ $session['open'] ? $session['remaining'].' places remaining' : ($session['can_waitlist'] ? 'Waitlist open' : 'Closed') }}
                    </span>
                    @if(!$session['open'])<small>{{ $session['reason'] }}</small>@endif
                </li>
            @endforeach
            </ul>
        @elseif($availability['capacity'] !== null)
            <p>{{ $availability['count'] }} registered · {{ $availability['remaining'] }} places remaining (maximum {{ $availability['capacity'] }})</p>
            <p>Minimum {{ $details['trial_min'] }} participants needed. {{ $availability['minimum_reached'] ? 'Minimum interest reached; this is not yet a confirmed booking.' : 'The trial will only proceed when the required minimum is reached and confirmed.' }}</p>
        @else<p>Individual trial: register an enquiry and the provider will arrange availability. There is no shared group-seat limit.</p>@endif
        @if($mine->isNotEmpty())<p>Your registrations: @foreach($mine as $interest){{ $interest->child_name }} ({{ $interest->status }})@unless($loop->last), @endunless @endforeach. <a href="{{ route('parent.dashboard') }}">Manage on your dashboard</a>.</p>@endif
        @if($errors->any())<div class="error" role="alert">{{ $errors->first() }}</div>@endif
        @if($sessions !== [] ? $openSessions === [] : ! $availability['open'])
            <p class="notice">{{ $sessions !== [] ? 'No trial sessions are accepting interest.' : $availability['reason'] }}</p>
        @elseif(auth()->user()?->role === 'parent')
            <form method="post" data-trial-form action="{{ route('parent.interest', $activityId) }}">@csrf
                <input type="hidden" name="expected_activity_version" value="{{ $activityVersion }}">
                @if($sessions !== [])
                    <label for="trial_session">Trial session (Sydney time)</label>
                    <select id="trial_session" name="trial_session" required>
                        <option value="">Choose a session</option>
                        @foreach($openSessions as $number => $session)
                            <option value="{{ $number }}" data-intent="{{ $session['open'] ? 'register' : 'waitlist' }}" @selected((string) old('trial_session') === (string) $number)>{{ $session['start'] }}{{ $session['can_waitlist'] ? ' · Waitlist' : '' }}</option>
                        @endforeach
                    </select>
                @endif
                <div class="fields">
                    <div><label for="child_name">Child's first name or nickname</label><input id="child_name" name="child_name" maxlength="80" value="{{ old('child_name') }}" required autocomplete="off"></div>
                    <div><label for="child_age">Child's age</label><input id="child_age" name="child_age" type="number" min="{{ $details['min_age'] }}" max="{{ $details['max_age'] }}" value="{{ old('child_age') }}" required></div>
                </div>
                <label class="check-row"><input type="checkbox" name="interest_terms" value="1" required> I have reviewed the activity details. I understand this is free interest registration, not a confirmed booking or payment.</label>
                @if($sessions === [] || collect($openSessions)->contains('open', true))
                    <button type="submit" data-intent="register">Register interest →</button>
                @endif
                @if(collect($openSessions)->contains('can_waitlist', true))
                    <button type="submit" data-intent="waitlist" name="waitlist" value="1">Join waitlist →</button>
                @endif
            </form>
        @elseif(auth()->guest())<a class="button" href="{{ route('login', ['activity' => $activityId]) }}">Sign in as a parent to continue</a>
        @else<p>Parent accounts can register a child's interest. Your account is {{ auth()->user()->role }}.</p>@endif
    </section>
    <section class="panel discovery-programme">
        <h2>About the activity</h2>
        <p class="preserve-lines">{{ $details['description'] }}</p>
        @if(!empty($details['bring']))<p><strong>What to bring</strong><br>{{ $details['bring'] }}</p>@endif
        <h2>Usual lessons</h2>
        @if($details['regular_format'] === 'individual')<p class="preserve-lines">{{ $details['usual_availability'] }}</p>
        @elseif(!empty($details['class_times']))<p class="preserve-lines">{{ $details['class_times'] }}</p>
        @else<p>{{ $details['regular_day'] }} · {{ $details['regular_time'] }}</p>@endif
        @include('provider.partials.prices')
    </section>
    </div>
</div>
<script type="module" src="{{ asset('js/trial-selection.mjs') }}"></script>
@endsection
