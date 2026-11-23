@extends('layouts.app')
@section('title', 'Provider dashboard · GoTry')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/provider.css') }}">
@endpush
@section('content')
<div class="provider-ui provider-dashboard">
    <section class="provider-banner">
        <div>
            <h1>Your activities</h1>
            <p>Manage your listings, trial sessions and seat offers.</p>
        </div>
        <div class="provider-dashboard-actions">
            <a class="provider-inbox-link" href="{{ route('notifications.index') }}" aria-label="Notifications" title="Notifications">@include('layouts.navigation-icon', ['icon' => 'bell'])</a>
            <a class="button" href="{{ route('provider.create') }}">{{ $draftStep ? 'Resume listing →' : '+ List an activity' }}</a>
        </div>
    </section>

    @if($draftStep)
        <div class="provider-draft">
            <div><strong>Listing in progress</strong><p>Step {{ $draftStep }} of 6 · {{ \App\Services\ActivityDraft::STEPS[$draftStep] }}</p></div>
            <a href="{{ route('provider.create') }}">Continue draft →</a>
        </div>
    @endif

    <div class="provider-workspace">
    <nav class="provider-sidebar" aria-label="Provider workspace">
        <a href="{{ route('provider.dashboard') }}" aria-current="page">@include('layouts.navigation-icon', ['icon' => 'list'])<span>My listings</span></a>
        <a href="{{ route('provider.create') }}">@include('layouts.navigation-icon', ['icon' => 'add'])<span>{{ $draftStep ? 'Continue draft' : 'New listing' }}</span></a>
        <a href="{{ route('activities.index') }}">@include('layouts.navigation-icon', ['icon' => 'home'])<span>Explore activities</span></a>
    </nav>
    <div class="provider-list-content">
    <div class="provider-list-heading">
        <h2>Your listings</h2>
        <span>{{ $activities->total() }} submitted</span>
    </div>
    <div class="grid provider-list">
        @forelse($activities as $activity)
            <article class="provider-list-row">
                <div class="provider-row-content">
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
                </div>
                <a class="provider-manage-link" href="{{ route('provider.activity', $activity) }}">Manage activity <span aria-hidden="true">→</span></a>
            </article>
        @empty
            <div class="provider-empty">
                <h3>No submitted activities yet</h3>
                <p>Add your activity, introductory trial and regular lesson details to submit your first listing for review.</p>
                <a class="button" href="{{ route('provider.create') }}">Create your first listing →</a>
            </div>
        @endforelse
    </div>
    {{ $activities->links() }}
    </div>
    </div>
</div>
@endsection
