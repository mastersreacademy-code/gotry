@extends('layouts.app')
@section('title', $title.' · GoTry')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/discovery.css') }}">
@endpush
@section('content')
<div class="discovery-ui activity-page">
    @php
        $openSessions = array_filter($sessions, fn ($session) => $session['open'] || $session['can_waitlist']);
        $canRegister = $sessions === [] ? $availability['open'] : collect($openSessions)->contains('open', true);
        $canWaitlist = collect($openSessions)->contains('can_waitlist', true);
    @endphp
    @php($soccer = $category === 'Sports & Fitness' && \Illuminate\Support\Str::contains(\Illuminate\Support\Str::lower($title), 'soccer'))
    <div class="activity-masthead">
        <a class="activity-wordmark" href="{{ route('home') }}"><img src="{{ asset('images/gotry-activity-logo.webp') }}" alt="GoTry home" width="146" height="97"></a>
        <div class="activity-organiser"><strong>{{ $details['business_name'] ?? $title }}</strong><small>{{ $category }}</small></div>
        <span class="discovery-badge {{ $canRegister ? 'discovery-badge-open' : ($canWaitlist ? 'discovery-badge-wait' : '') }}">{{ $canRegister ? 'Interest open' : ($canWaitlist ? 'Waitlist open' : 'Trial closed') }}</span>
    </div>
    <section @class(['activity-hero', 'activity-hero-photo' => $soccer])>
        <div class="activity-hero-copy">
            <p class="activity-category">Try before you enrol</p>
            <h1>{{ $title }}</h1>
            <p class="activity-description preserve-lines">{{ $details['description'] }}</p>
        </div>
        @if($soccer)
            <img src="{{ asset('images/soccer-trial.webp') }}" alt="" width="1200" height="800" fetchpriority="high">
        @endif
    </section>
    <section class="panel activity-overview">
    <h2>Programme overview</h2>
    <dl class="activity-facts">
        <div><dt>Ages</dt><dd>{{ $details['min_age'] }}–{{ $details['max_age'] }} years</dd></div>
        <div><dt>Venue</dt><dd>{{ $details['venue'] }}</dd></div>
        <div><dt>Usual lessons</dt><dd>{{ $details['regular_format'] === 'individual' ? 'Individual lessons' : (!empty($details['class_times']) ? 'Multiple class times' : $details['regular_day']) }}</dd></div>
        <div><dt>Programme</dt><dd>{{ $details['regular_format'] === 'group' ? $details['lessons_per_term'].' lessons per term' : 'Ask about availability' }}</dd></div>
    </dl>
    </section>
    <div class="discovery-detail">
    <section class="panel discovery-trial">
        <p class="eyebrow">Free introductory trial</p>
        <h2>{{ $details['trial_type'] === 'group' ? 'Trial class' : 'Individual trial' }}</h2>
        @if($details['trial_type'] === 'group')
            <dl class="activity-trial-facts">
                @if($sessions === [])
                    <div><dt>Trial date</dt><dd><time datetime="{{ $details['trial_date'] }}">{{ \Illuminate\Support\Carbon::parse($details['trial_date'])->format('D, j M Y') }}</time></dd></div>
                    <div><dt>Start time</dt><dd>{{ $details['trial_time'] }} · Sydney</dd></div>
                @endif
                <div><dt>Group size{{ $sessions !== [] ? ' per session' : '' }}</dt><dd>{{ $details['trial_min'] }}–{{ $availability['capacity'] }} children</dd></div>
            </dl>
        @else
            <p class="preserve-lines">Ask provider about availability: {{ $details['trial_availability'] }}</p>
        @endif
        <p><strong>{{ $details['trial_duration'] }} minutes</strong> · One introductory trial per child</p>
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
    </section>
    <section class="panel discovery-programme">
        <h2>Programme price @if(count($prices) && $details['regular_format'] === 'group')<small>({{ $details['lessons_per_term'] }} lessons)</small>@endif</h2>
        @if(!empty($details['bring']))<p><strong>What to bring</strong><br>{{ $details['bring'] }}</p>@endif
        @if($details['regular_format'] === 'individual')<p class="preserve-lines">{{ $details['usual_availability'] }}</p>
        @elseif(!empty($details['class_times']))<p class="preserve-lines">{{ $details['class_times'] }}</p>
        @else<p>{{ $details['regular_day'] }} · {{ $details['regular_time'] }}</p>@endif
        @if(count($prices))
            <div class="activity-prices">
                @foreach($prices as $price)
                    <div class="activity-price">
                        <h3>{{ $price['label'] }}</h3>
                        @if($price['label'] === 'Standard group' && isset($details['regular_standard'], $details['regular_max']))
                            <small>{{ $details['regular_standard'] }}–{{ $details['regular_max'] }} children</small>
                        @elseif($price['label'] === 'Small group' && isset($details['regular_min'], $details['regular_standard']) && $details['regular_min'] < $details['regular_standard'])
                            <small>{{ $details['regular_min'] }}–{{ $details['regular_standard'] - 1 }} children</small>
                        @endif
                        <p class="activity-price-amount"><strong>${{ number_format($price['total'] / 100, 2) }}</strong> {{ $price['discount'] > 0 ? strtolower($price['period']) : ($details['regular_format'] === 'group' ? 'per term' : 'per lesson') }}</p>
                        @if($price['discount'] > 0)
                            <small>Regular price ${{ number_format($price['regular'] / 100, 2) }} · Save ${{ number_format($price['discount'] / 100, 2) }}</small>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </section>
    </div>
    <section class="panel activity-deadline">
        <div>
            <h2>{{ $details['trial_type'] === 'group' ? 'Minimum numbers deadline' : 'Enquiry deadline' }}</h2>
            @if($details['trial_type'] === 'group')
                <p>At least {{ $details['trial_min'] }} participants are needed for each trial session to proceed.</p>
            @endif
        </div>
        <div><strong>Register interest by</strong><br>
            <time datetime="{{ $details['deadline'] }}">{{ \Illuminate\Support\Carbon::parse($details['deadline'])->format('D, j M Y') }}</time>
        </div>
    </section>
    @if($canRegister || $canWaitlist)
        <section class="panel activity-how">
            <h2>How it works</h2>
            <ol class="activity-steps">
                <li>{{ $canRegister ? 'Register interest for free.' : 'Join the waitlist for free.' }}</li>
                <li>{{ $details['trial_type'] === 'individual' ? 'Arrange a trial time with the provider.' : 'Check your dashboard for updates' }}</li>
                <li>Attend your trial once confirmed</li>
            </ol>
        </section>
    @endif
    @if(($canRegister || $canWaitlist) && (auth()->guest() || auth()->user()->role === 'parent'))
        <div class="activity-final-action">
            <a class="button" href="#trial">{{ $canRegister ? 'Register interest for the trial' : 'Join the trial waitlist' }} →</a>
            <p>Registering interest is free. No payment is collected on this page.</p>
        </div>
    @endif
    <section class="panel discovery-trial activity-registration" id="trial">
        <h2>{{ $canRegister ? 'Register interest for the trial' : ($canWaitlist ? 'Join the trial waitlist' : 'Trial availability') }}</h2>
        @if($mine->isNotEmpty())<p>Your registrations: @foreach($mine as $interest){{ $interest->child_name }} ({{ $interest->status }})@unless($loop->last), @endunless @endforeach. <a href="{{ route('parent.dashboard') }}">Manage on your dashboard</a>.</p>@endif
        @if($errors->any())<div class="error" role="alert">{{ $errors->first() }}</div>@endif
        @if(!$canRegister && !$canWaitlist)
            <p class="notice">{{ $sessions !== [] ? 'No trial sessions are accepting interest.' : $availability['reason'] }}</p>
        @elseif(auth()->user()?->role === 'parent')
            <form method="post" data-trial-form action="{{ route('parent.interest', $activityId) }}">@csrf
                <input type="hidden" name="return_to_trial" value="1">
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
                @if($canRegister)
                    <button type="submit" data-intent="register">Register interest →</button>
                @endif
                @if($canWaitlist)
                    <button type="submit" data-intent="waitlist" name="waitlist" value="1">Join waitlist →</button>
                @endif
            </form>
        @elseif(auth()->guest())<a class="button" href="{{ route('login', ['activity' => $activityId]) }}">Sign in as a parent to continue</a>
        @else<p>Parent accounts can register a child's interest. Your account is {{ auth()->user()->role }}.</p>@endif
    </section>
    <a class="activity-back" href="{{ route('activities.index') }}">← Explore activities</a>
</div>
<script type="module" src="{{ asset('js/trial-selection.mjs') }}"></script>
@endsection
