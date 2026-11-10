@extends('layouts.app')
@section('title', $activity->title.' · GoTry')
@section('content')
<section class="panel">
    <a href="{{ route('provider.dashboard') }}">← Your activities</a>
    <h1>{{ $activity->title }}</h1><span class="status status-{{ $activity->status }}">{{ $activity->statusLabel() }}</span>
    @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
    @if($activity->review_reason)<p class="notice"><strong>Admin feedback:</strong> {{ $activity->review_reason }}</p>@endif
    @include('provider.partials.review')
    @include('provider.partials.prices')
</section>
@endsection
