@extends('layouts.app')
@section('title', 'Explore activities · GoTry')
@section('content')
<h1>Explore trial activities</h1>
<p>Discover introductory activities from approved providers.</p>
<div class="grid">@forelse($activities as $activity)<article><p class="eyebrow">{{ $activity->category }}</p><h2><a href="{{ route('activities.show', $activity) }}">{{ $activity->title }}</a></h2><p>{{ $activity->details['venue'] }}</p><p>Ages {{ $activity->details['min_age'] }}–{{ $activity->details['max_age'] }}</p></article>@empty<p>New activities are coming soon.</p>@endforelse</div>
{{ $activities->links() }}
@endsection
