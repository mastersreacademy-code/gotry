@extends('layouts.app')
@section('title', 'Review activities · GoTry')
@section('content')
@include('admin.nav')
<h1>Activity reviews</h1>
<form method="get" class="admin-filters"><label>Activity title<input name="q" value="{{ request('q') }}" maxlength="160"></label><label>Status<select name="status"><option value="">All statuses</option>@foreach(\App\Models\Activity::STATUSES as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select></label><button>Filter</button><a href="{{ route('admin.activities') }}">Clear</a></form>
<ul class="admin-records">
@forelse($activities as $activity)
    <li><article class="admin-record">
        <div>
            <h2><a href="{{ route('admin.activity', $activity) }}">{{ $activity->title }}</a></h2>
            <p>{{ $activity->category }} · {{ $activity->provider->email }}</p>
        </div>
        <div class="admin-record-meta">
            <span class="status status-{{ $activity->status }}">{{ $activity->statusLabel() }}</span>
            <a class="button secondary" href="{{ route('admin.activity', $activity) }}" aria-label="Open review for {{ $activity->title }}">Open review →</a>
        </div>
    </article></li>
@empty
    <li class="admin-empty">No matching activities.</li>
@endforelse
</ul>
{{ $activities->links() }}
@endsection
