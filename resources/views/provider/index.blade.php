@extends('layouts.app')
@section('title', 'Provider dashboard · GoTry')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/provider.css') }}">
@endpush
@section('content')
<div class="provider-ui">
    <section class="provider-banner">
        <div>
            <p class="eyebrow">Provider dashboard</p>
            <h1>Hello, {{ auth()->user()->name }}</h1>
            <p>Manage your listings, trial sessions and seat offers.</p>
        </div>
        <a class="button" href="{{ route('provider.create') }}">{{ $draftStep ? 'Resume listing →' : 'List an activity →' }}</a>
    </section>

    @if($draftStep)
        <div class="provider-draft">
            <div><strong>Listing in progress</strong><p>Step {{ $draftStep }} of 5 · {{ \App\Services\ActivityDraft::STEPS[$draftStep] }}</p></div>
            <a href="{{ route('provider.create') }}">Continue draft →</a>
        </div>
    @endif

    <div class="provider-list-heading">
        <h2>Your listings</h2>
        <span>{{ $activities->total() }} submitted</span>
    </div>
    <div class="grid provider-list">
        @forelse($activities as $activity)
            <article>
                <div class="provider-list-meta">
                    <span>{{ $activity->category }}</span>
                    <span class="status status-{{ $activity->status }}">{{ $activity->statusLabel() }}</span>
                </div>
                <h3><a href="{{ route('provider.activity', $activity) }}">{{ $activity->title }}</a></h3>
                @if(!empty($activity->details['venue']))<p>{{ $activity->details['venue'] }}</p>@endif
                <p>{{ match ($activity->status) {
                    'pending_approval' => 'Waiting for GoTry to review your listing.',
                    'rejected' => 'Review the feedback on this listing.',
                    'pending_registrations' => 'Check trial sessions and interest.',
                    default => 'See the latest trial status.',
                } }}</p>
            </article>
        @empty
            <div class="provider-empty">
                <h3>No submitted activities yet</h3>
                <p>Add your activity, introductory trial and regular lesson details to submit your first listing for review.</p>
            </div>
        @endforelse
    </div>
    {{ $activities->links() }}
</div>
@endsection
