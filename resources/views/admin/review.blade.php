@extends('layouts.app')
@section('title', 'Review '.$activity->title.' · GoTry')
@section('content')
<section class="panel">
    <a href="{{ route('admin.dashboard') }}">← All activities</a>
    <h1>{{ $activity->title }}</h1><span class="status status-{{ $activity->status }}">{{ $activity->statusLabel() }}</span>
    @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
    @if($errors->any())<div class="error" role="alert">{{ $errors->first() }}</div>@endif
    @include('provider.partials.review')
    @include('provider.partials.prices')
    <p>Provider declarations accepted: {{ $activity->details['declarations']['accepted_at'] ?? 'Not recorded' }}</p>
    @if($activity->status === 'pending_approval')
    <form method="post" action="{{ route('admin.decision', $activity) }}">@csrf
        <input type="hidden" name="expected_version" value="{{ $activity->version }}">
        <label for="reason">Reason (required for rejection)</label><textarea id="reason" name="reason" maxlength="1000">{{ old('reason') }}</textarea>
        <div class="actions"><button type="submit" name="action" value="approve">Approve listing</button><button class="secondary" type="submit" name="action" value="reject">Reject listing</button></div>
    </form>
    @else<p>{{ $activity->review_reason }}</p>@endif
</section>
@endsection
