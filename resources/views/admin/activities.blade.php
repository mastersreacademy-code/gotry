@extends('layouts.app')
@section('title', 'Review activities · GoTry')
@section('content')
<h1>Activity reviews</h1>
<div class="grid">@forelse($activities as $activity)<article>
    <span class="status status-{{ $activity->status }}">{{ $activity->statusLabel() }}</span>
    <h2><a href="{{ route('admin.activity', $activity) }}">{{ $activity->title }}</a></h2><p>{{ $activity->category }}</p>
</article>@empty<p>No submitted activities yet.</p>@endforelse</div>
{{ $activities->links() }}
@endsection
