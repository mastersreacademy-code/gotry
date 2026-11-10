@extends('layouts.app')
@section('title', 'Notifications · GoTry')
@section('content')
<div class="heading-row"><h1>Notifications</h1><a href="{{ route('dashboard') }}">Your dashboard →</a></div>
<p>Unread in inbox: {{ $unread }} · Queued: {{ $pending }}</p>
@if($pending > 0)<p class="notice">More notifications are waiting. <a href="{{ route('notifications.index') }}">Refresh to load the next batch</a>.</p>@endif
<p class="text-small">In-app updates are delivered when you open this page. No email or push notification is sent.</p>
@forelse($notifications as $notification)
<article class="inbox-notice {{ $notification->read_at ? 'is-read' : '' }}">
    <div class="heading-row"><h2>{{ $notification->title }}</h2><span class="status {{ $notification->read_at ? 'status-withdrawn' : 'status-pending_registrations' }}">{{ $notification->read_at ? 'Read' : 'Unread' }}</span></div>
    <p>{{ $notification->message }}</p>
    <p class="text-small">{{ $notification->created_at->timezone('Australia/Sydney')->format('j M Y, g:i a') }} · Sydney</p>
    <div class="notice-actions">
        <a href="{{ $notification->destination() }}">Open dashboard details →</a>
        @unless($notification->read_at)<form method="post" action="{{ route('notifications.read', $notification) }}">@csrf<button class="secondary" type="submit">Mark as read</button></form>@endunless
    </div>
</article>
@empty<p>Your inbox is clear. New updates will appear here after relevant activity changes.</p>@endforelse
{{ $notifications->links() }}
@endsection
