@extends('layouts.app')
@section('title', 'Review '.$activity->title.' · GoTry')
@section('content')
@include('admin.nav')
<section class="panel admin-trial-review">
    <a href="{{ route('admin.activities') }}">← All activities</a>
    <h1>{{ $activity->title }}</h1><span class="status status-{{ $activity->status }}">{{ $activity->statusLabel() }}</span>
    <p>Active interests: {{ $interestCount }}@if(($activity->details['trial_type'] ?? '')==='group') · Minimum {{ $activity->details['trial_min'] }} · Maximum {{ $activity->details['trial_max'] }} per trial @endif</p>
    @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
    @if($errors->any())<div class="error" role="alert">{{ $errors->first() }}</div>@endif
    @include('provider.partials.review')
    @include('provider.partials.prices')
    @include('provider.partials.trial-sessions')
    <p>Provider declarations accepted: {{ $activity->details['declarations']['accepted_at'] ?? 'Not recorded' }}</p>
    @if($activity->status === 'pending_approval')
    <form method="post" action="{{ route('admin.decision', $activity) }}">@csrf
        <input type="hidden" name="expected_version" value="{{ $activity->version }}">
        <label for="reason">Reason (required for rejection)</label><textarea id="reason" name="reason" maxlength="1000">{{ old('reason') }}</textarea>
        <div class="actions"><button type="submit" name="action" value="approve">Approve listing</button><button class="secondary" type="submit" name="action" value="reject">Reject listing</button></div>
    </form>
    @else<p>{{ $activity->review_reason }}</p>@endif
    @if(in_array($activity->status,['pending_registrations','proceeding_pending_payments'],true))
    <h2>Trial status controls</h2><p>Proceeding is not proof of payment. No parent is charged by these actions.</p>
    <form method="post" action="{{ route('admin.lifecycle',$activity) }}">@csrf<input type="hidden" name="expected_version" value="{{ $activity->version }}">
        @if($sessions !== [])
        <label for="trial_session">Trial session</label>
        <select id="trial_session" name="trial_session">
            <option value="">All sessions (admin cancellation only)</option>
            @foreach($sessions as $number => $session)
                @if(in_array($session['status'], ['pending_registrations', 'proceeding_pending_payments'], true))
                    <option value="{{ $number }}">{{ $session['start'] }} (Sydney)</option>
                @endif
            @endforeach
        </select>
        @endif
        <label for="lifecycle-reason">Reason (required for admin cancellation)</label><textarea id="lifecycle-reason" name="reason" maxlength="1000"></textarea>
        <div class="actions">@if($activity->status==='pending_registrations' && ($activity->details['trial_type'] ?? '')==='group')<button name="action" value="confirm_minimum">Mark proceeding</button><button class="secondary" name="action" value="cancel_low_numbers">Cancel below minimum</button>@endif<button class="secondary" name="action" value="cancel_admin">Cancel as admin</button></div>
    </form>@endif
</section>
@endsection
