@extends('layouts.app')
@section('title','Audit history · GoTry')
@section('content')
@include('admin.nav')<h1>Audit history</h1>
<nav class="admin-nav" aria-label="Audit category">@foreach(['activity'=>'Activities','interest'=>'Registrations','account'=>'Account access'] as $value=>$label)<a href="{{ route('admin.audit',['type'=>$value]) }}" @if($type===$value) aria-current="page" @endif>{{ $label }}</a>@endforeach</nav>
<p>History is read-only. Registration audit rows exclude child names and ages.</p>
<div class="table-wrap"><table><thead><tr><th>When / actor</th><th>Subject</th><th>Change</th><th>Reason</th></tr></thead><tbody>
@forelse($events as $event)
@php($scheduleChange = json_decode($event->schedule_change ?? 'null', true))
<tr><td>{{ $event->created_at }} UTC<br>{{ $event->actor }}</td><td>{{ $event->subject }}@if(!empty($event->trial_session) && !isset($scheduleChange['from_session']))<br>Trial session {{ $event->trial_session }}@endif</td><td>{{ $event->action }}<br>{{ $event->from_status ?? 'New' }} → {{ $event->to_status }}@if($scheduleChange)<br>{{ $scheduleChange['from_start'] }} → {{ $scheduleChange['to_start'] }} (Sydney)@endif</td><td>{{ $event->reason ?? '—' }}</td></tr>@empty<tr><td colspan="4">No events yet.</td></tr>@endforelse
</tbody></table></div>{{ $events->links() }}
@endsection
