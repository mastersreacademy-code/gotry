@extends('layouts.app')
@section('title', $title.' · GoTry')
@section('content')
<section class="panel">
    <a href="{{ route('activities.index') }}">← Explore activities</a><p class="eyebrow">{{ $category }}</p><h1>{{ $title }}</h1>
    <p class="preserve-lines">{{ $details['description'] }}</p>
    <h2>Programme overview</h2><p>Ages {{ $details['min_age'] }}–{{ $details['max_age'] }} · {{ $details['venue'] }}</p>
    @if(!empty($details['bring']))<p>Bring: {{ $details['bring'] }}</p>@endif
    <h2>Free introductory trial</h2>
    @if($details['trial_type'] === 'group')<p>{{ $details['trial_date'] }} at {{ $details['trial_time'] }} · Australia/Sydney</p>@else<p class="preserve-lines">Ask provider about availability: {{ $details['trial_availability'] }}</p>@endif
    <p>{{ $details['trial_duration'] }} minutes · Register interest by {{ $details['deadline'] }}</p>
    <h2>Usual lessons</h2>
    @if($details['regular_format'] === 'individual')<p class="preserve-lines">{{ $details['usual_availability'] }}</p>
    @elseif(!empty($details['class_times']))<p class="preserve-lines">{{ $details['class_times'] }}</p>
    @else<p>{{ $details['regular_day'] }} · {{ $details['regular_time'] }}</p>@endif
    @include('provider.partials.prices')
    <section class="registration-section">
        <h2>Register interest for the trial</h2>
        @if($availability['capacity'] !== null)
            <p>{{ $availability['count'] }} registered · {{ $availability['remaining'] }} places remaining (maximum {{ $availability['capacity'] }})</p>
            <p>Minimum {{ $details['trial_min'] }} participants needed. {{ $availability['minimum_reached'] ? 'Minimum interest reached; this is not yet a confirmed booking.' : 'The trial will only proceed when the required minimum is reached and confirmed.' }}</p>
        @else<p>Individual trial: register an enquiry and the provider will arrange availability. There is no shared group-seat limit.</p>@endif
        @if($mine->isNotEmpty())<p>Your registrations: @foreach($mine as $interest){{ $interest->child_name }} ({{ $interest->status === 'interested' ? 'registered' : 'withdrawn' }})@unless($loop->last), @endunless @endforeach. <a href="{{ route('parent.dashboard') }}">Manage on your dashboard</a>.</p>@endif
        @if($errors->any())<div class="error" role="alert">{{ $errors->first() }}</div>@endif
        @if(! $availability['open'])<p class="notice">{{ $availability['reason'] }}</p>
        @elseif(auth()->user()?->role === 'parent')
            <form method="post" action="{{ route('parent.interest', $activityId) }}">@csrf
                <input type="hidden" name="expected_activity_version" value="{{ $activityVersion }}">
                <div class="fields">
                    <div><label for="child_name">Child's first name or nickname</label><input id="child_name" name="child_name" maxlength="80" value="{{ old('child_name') }}" required autocomplete="off"></div>
                    <div><label for="child_age">Child's age</label><input id="child_age" name="child_age" type="number" min="{{ $details['min_age'] }}" max="{{ $details['max_age'] }}" value="{{ old('child_age') }}" required></div>
                </div>
                <label class="check-row"><input type="checkbox" name="interest_terms" value="1" required> I have reviewed the activity details. I understand this is free interest registration, not a confirmed booking or payment.</label>
                <button type="submit">Register interest →</button>
            </form>
        @elseif(auth()->guest())<a class="button" href="{{ route('login') }}">Sign in as a parent to register interest</a>
        @else<p>Parent accounts can register a child's interest. Your account is {{ auth()->user()->role }}.</p>@endif
    </section>
    <p class="text-small">No payment is collected here. Trial confirmation, session management and payment requests are still being migrated.</p>
</section>
@endsection
