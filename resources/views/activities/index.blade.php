@extends('layouts.app')
@section('title', 'Explore activities · GoTry')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/discovery.css') }}">
@endpush
@section('content')
<div class="discovery-ui discovery-directory">
    <div class="discovery-heading">
        <h1>Explore trial activities</h1>
        <p>Find their next favourite activity. Start with a free introductory trial.</p>
    </div>
    @if($errors->any())<p class="error" role="alert">{{ $errors->first() }}</p>@endif
    <form class="discovery-filters panel" method="get" action="{{ route('activities.index') }}">
        <div>
            <label for="activity-search">Activity name</label>
            <input id="activity-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="Try piano, soccer or swimming">
        </div>
        <div>
            <label for="activity-category">Category</label>
            <select id="activity-category" name="category">
                <option value="">All categories</option>
                @foreach($categories as $category)
                    <option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ $category }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit">Find activities</button>
        @if(!empty($filters['q']) || !empty($filters['category']))
            <a class="button secondary" href="{{ route('activities.index') }}">Clear filters</a>
        @endif
    </form>
    <div class="directory-results-heading">
        <h2>{{ !empty($filters['q']) || !empty($filters['category']) ? 'Search results' : 'Activities to explore' }}</h2>
        <p>{{ $activities->total() }} {{ $activities->total() === 1 ? 'activity' : 'activities' }}</p>
    </div>
    <div class="discovery-grid">
        @forelse($activities as $activity)
            @php
                $slots = collect($availability[$activity->id]);
                $open = $slots->where('open', true)->count();
                $waiting = $slots->contains('can_waitlist', true);
                $individual = ($activity->details['trial_type'] ?? null) === 'individual';
                $photo = match (true) {
                    $activity->category === 'Music' => 'music-trial.webp',
                    $activity->category === 'Sports & Fitness' && preg_match('/\bswim(?:ming)?\b/i', $activity->title) => 'swimming-trial.webp',
                    $activity->category === 'Sports & Fitness' && preg_match('/\b(?:soccer|football)\b/i', $activity->title) => 'soccer-trial.webp',
                    default => null,
                };
            @endphp
            <article class="discovery-card">
                @if($photo)
                    <img class="directory-card-photo" src="{{ asset('images/'.$photo) }}" alt="" width="640" height="360" loading="lazy">
                @endif
                <div class="directory-card-body">
                <p class="eyebrow">{{ $activity->category }}</p>
                <h2><a href="{{ route('activities.show', $activity) }}">{{ $activity->title }}</a></h2>
                <p>{{ $activity->details['venue'] }} · Ages {{ $activity->details['min_age'] }}–{{ $activity->details['max_age'] }}</p>
                <span @class(['discovery-badge', 'discovery-badge-open' => $open, 'discovery-badge-wait' => !$open && $waiting])>
                    {{ $open ? ($individual ? 'Enquiries open' : ($open === 1 ? '1 session open' : $open.' sessions open')) : ($waiting ? 'Waitlist open' : 'Registrations closed') }}
                </span>
                @if($open && $waiting)<span class="discovery-badge discovery-badge-wait">Waitlist also open</span>@endif
                <a class="directory-card-action" href="{{ route('activities.show', $activity) }}" aria-label="View {{ $activity->title }}">View activity <span aria-hidden="true">→</span></a>
                </div>
            </article>
        @empty
            <article class="discovery-card discovery-empty"><h2>{{ !empty($filters['q']) || !empty($filters['category']) ? 'No activities match these filters.' : 'New activities are coming soon.' }}</h2>@if(!empty($filters['q']) || !empty($filters['category']))
                <p>Try another activity name or choose a different category.</p>
                @endif</article>
        @endforelse
    </div>
    {{ $activities->links() }}
</div>
@endsection
