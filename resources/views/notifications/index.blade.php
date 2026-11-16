@extends('layouts.app')
@section('title', 'Notifications · GoTry')
@section('content')
<div class="heading-row"><h1>Notifications</h1><a href="{{ route('dashboard') }}">Your dashboard →</a></div>
@include('notifications.list')
@endsection
