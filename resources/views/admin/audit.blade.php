@extends('layouts.app')
@section('title','Audit history · GoTry')
@section('content')
@include('admin.nav')<h1>Audit history</h1>
<nav class="admin-nav" aria-label="Audit category">@foreach(['activity'=>'Activities','interest'=>'Registrations','account'=>'Account access'] as $value=>$label)<a href="{{ route('admin.audit',['type'=>$value]) }}" @if($type===$value) aria-current="page" @endif>{{ $label }}</a>@endforeach</nav>
<p>History is read-only. Registration audit rows exclude child names and ages.</p>
<ol class="admin-records">
@forelse($events as $event)
    @php($scheduleChange = json_decode($event->schedule_change ?? 'null', true))
    <li><article class="admin-record">
        <div>
            <h2>{{ $event->subject }}</h2>
            <p>{{ $event->created_at }} UTC · {{ $event->actor ?? 'GoTry automation' }}</p>
        </div>
        <dl class="admin-audit-fields">
            <div>
                <dt>Change</dt>
                <dd>{{ $event->action }} · {{ $event->from_status ?? 'New' }} → {{ $event->to_status }}</dd>
            </div>
            @if(!empty($event->trial_session) && !isset($scheduleChange['from_session']))
                <div><dt>Trial session</dt><dd>Trial session {{ $event->trial_session }}</dd></div>
            @endif
            @if($scheduleChange)
                <div><dt>Schedule change</dt><dd>{{ $scheduleChange['from_start'] }} → {{ $scheduleChange['to_start'] }} (Sydney)</dd></div>
            @endif
            @if(!empty($event->offer_expires_at))
                <div><dt>Offer deadline</dt><dd>{{ \Carbon\CarbonImmutable::parse($event->offer_expires_at, config('app.timezone'))->setTimezone('Australia/Sydney')->format('Y-m-d H:i T') }}</dd></div>
            @endif
            @if($event->reason !== null)
                <div><dt>Reason</dt><dd>{{ $event->reason }}</dd></div>
            @endif
        </dl>
    </article></li>
@empty
    <li class="admin-empty">No events yet.</li>
@endforelse
</ol>
{{ $events->links() }}
@endsection
