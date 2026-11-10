@extends('layouts.app')
@section('title', ucfirst($role).' dashboard · GoTry')
@section('content')
<section class="panel">
    <p class="eyebrow">{{ ucfirst($role) }} dashboard</p>
    <h1>Hello, {{ auth()->user()->name }}</h1>
    <p>You are signed in as {{ auth()->user()->email }}.</p>
    <p class="notice">Your account is ready. Activity listings, registrations and management tools are being migrated to this version.</p>
</section>
@endsection
