@extends('layouts.app')
@section('title', 'Explore activities · GoTry')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/discovery.css') }}">
@endpush
@section('content')
<div class="discovery-ui">
    <div class="discovery-heading">
        <h1>Explore trial activities</h1>
        <p>Find a free introductory trial that suits your child.</p>
    </div>
    @if($errors->any())<p class="error" role="alert">{{ $errors->first() }}</p>@endif
    <form class="discovery-filters panel" method="get" action="{{ route('activities.index') }}">
        <div>
            <label for="activity-search">Activity name</label>
            <input id="activity-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100">
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
    <div class="discovery-grid">
        @forelse($activities as $activity)
            @php
                $slots = collect($availability[$activity->id]);
                $open = $slots->where('open', true)->count();
                $waiting = $slots->contains('can_waitlist', true);
                $individual = ($activity->details['trial_type'] ?? null) === 'individual';
            @endphp
            <article class="discovery-card">
                <p class="eyebrow">{{ $activity->category }}</p>
                <h2><a href="{{ route('activities.show', $activity) }}">{{ $activity->title }}</a></h2>
                <p>{{ $activity->details['venue'] }} · Ages {{ $activity->details['min_age'] }}–{{ $activity->details['max_age'] }}</p>
                <span @class(['discovery-badge', 'discovery-badge-open' => $open, 'discovery-badge-wait' => !$open && $waiting])>
                    {{ $open ? ($individual ? 'Enquiries open' : ($open === 1 ? '1 session open' : $open.' sessions open')) : ($waiting ? 'Waitlist open' : 'Registrations closed') }}
                </span>
                @if($open && $waiting)<span class="discovery-badge discovery-badge-wait">Waitlist also open</span>@endif
            </article>
        @empty
            <article class="discovery-card discovery-empty"><h2>{{ !empty($filters['q']) || !empty($filters['category']) ? 'No activities match these filters.' : 'New activities are coming soon.' }}</h2></article>
        @endforelse
    </div>
    {{ $activities->links() }}
</div>
@endsection
