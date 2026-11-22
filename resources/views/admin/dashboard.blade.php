@extends('layouts.app')
@section('title','Super admin · GoTry')
@section('content')
<div class="admin-workspace">
@include('admin.nav')
<div class="admin-content">
@if(session('status'))<p class="notice" role="status">{{ session('status') }}</p>@endif
@if($errors->any())<div class="error" role="alert">{{ $errors->first() }}</div>@endif


<section class="admin-banner"><div><h1>GoTry overview</h1><p>Review activities, manage accounts and track registrations.</p></div><a class="button" href="{{ route('admin.activities', ['status' => 'pending_approval']) }}">Review activities →</a></section>
<div class="admin-stats">@foreach($counts as $label=>$count)<article><strong>{{ $count }}</strong><p>{{ $label }}</p></article>@endforeach</div>
<h2>Awaiting review</h2>
@forelse($activities as $activity)<article class="inbox-notice"><h3><a href="{{ route('admin.activity',$activity) }}">{{ $activity->title }}</a></h3><p>{{ $activity->provider->name }} · {{ $activity->provider->email }}</p></article>@empty<p>No activities are waiting for approval.</p>@endforelse
<a href="{{ route('admin.activities') }}">View all activities →</a>
<p class="notice">Proceeding means minimum interest was reached. Check Number Paid on the activity review for verified Sandbox payments.</p>
</div>
</div>
@endsection
