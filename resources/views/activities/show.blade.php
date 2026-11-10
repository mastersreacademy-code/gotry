@extends('layouts.app')
@section('title', $title.' · GoTry')
@section('content')
<section class="panel">
    <a href="{{ route('activities.index') }}">← Explore activities</a><p class="eyebrow">{{ $category }}</p><h1>{{ $title }}</h1>
    <p class="preserve-lines">{{ $details['description'] }}</p>
    <h2>Programme overview</h2><p>Ages {{ $details['min_age'] }}–{{ $details['max_age'] }} · {{ $details['venue'] }}</p>
    @if(!empty($details['bring']))<p>Bring: {{ $details['bring'] }}</p>@endif
    <h2>Free introductory trial</h2>
    @if($details['trial_type'] === 'group')<p>{{ $details['trial_date'] }} at {{ $details['trial_time'] }} · Australia/Sydney</p>@else<p class="preserve-lines">Ask provider about availability: {{ $details['trial_availability'] }}</p>@endif
    <p>{{ $details['trial_duration'] }} minutes · Register interest by {{ $details['deadline'] }}</p>
    <h2>Usual lessons</h2>
    @if($details['regular_format'] === 'individual')<p class="preserve-lines">{{ $details['usual_availability'] }}</p>
    @elseif(!empty($details['class_times']))<p class="preserve-lines">{{ $details['class_times'] }}</p>
    @else<p>{{ $details['regular_day'] }} · {{ $details['regular_time'] }}</p>@endif
    @include('provider.partials.prices')
    <p class="notice">Online interest registration is being migrated. Bookings and payments are not available yet.</p>
</section>
@endsection
