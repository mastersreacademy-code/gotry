@extends('layouts.app')
@section('title','Super admin · GoTry')
@section('content')
@include('admin.nav')
<p class="eyebrow">Super admin workspace</p><h1>GoTry overview</h1>
<div class="admin-stats">@foreach($counts as $label=>$count)<article><strong>{{ $count }}</strong><p>{{ $label }}</p></article>@endforeach</div>
<h2>Awaiting review</h2>
@forelse($activities as $activity)<article class="inbox-notice"><h3><a href="{{ route('admin.activity',$activity) }}">{{ $activity->title }}</a></h3><p>{{ $activity->provider->name }} · {{ $activity->provider->email }}</p></article>@empty<p>No activities are waiting for approval.</p>@endforelse
<a href="{{ route('admin.activities') }}">View all activities →</a>
<p class="notice">Proceeding means minimum interest was reached. It does not mean payment was collected. Payment collection is not connected.</p>
@endsection
