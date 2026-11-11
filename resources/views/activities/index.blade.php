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
            <article class="discovery-card discovery-empty"><h2>New activities are coming soon.</h2></article>
        @endforelse
    </div>
    {{ $activities->links() }}
</div>
@endsection
