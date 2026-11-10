@extends('layouts.app')
@section('title', 'Review activities · GoTry')
@section('content')
@include('admin.nav')
<h1>Activity reviews</h1>
<form method="get" class="admin-filters"><label>Activity title<input name="q" value="{{ request('q') }}" maxlength="160"></label><label>Status<select name="status"><option value="">All statuses</option>@foreach(\App\Models\Activity::STATUSES as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select></label><button>Filter</button><a href="{{ route('admin.activities') }}">Clear</a></form>
<div class="grid">@forelse($activities as $activity)<article>
    <span class="status status-{{ $activity->status }}">{{ $activity->statusLabel() }}</span>
    <h2><a href="{{ route('admin.activity', $activity) }}">{{ $activity->title }}</a></h2><p>{{ $activity->category }} · {{ $activity->provider->email }}</p>
</article>@empty<p>No submitted activities yet.</p>@endforelse</div>
{{ $activities->links() }}
@endsection
