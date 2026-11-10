@extends('layouts.app')
@section('title', 'Provider dashboard · GoTry')
@section('content')
<div class="heading-row"><div><p class="eyebrow">Provider dashboard</p><h1>Hello, {{ auth()->user()->name }}</h1></div><a class="button" href="{{ route('provider.create') }}">List an activity →</a></div>
<p>Review your submitted activities or continue an unfinished draft.</p>
<div class="grid">@forelse($activities as $activity)<article>
    <span class="status status-{{ $activity->status }}">{{ $activity->statusLabel() }}</span>
    <h2><a href="{{ route('provider.activity', $activity) }}">{{ $activity->title }}</a></h2><p>{{ $activity->category }}</p>
</article>@empty<p>You have not submitted an activity yet.</p>@endforelse</div>
{{ $activities->links() }}
@endsection
