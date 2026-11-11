@extends('layouts.app')
@section('title', $mode === 'register' ? 'Create account · GoTry' : 'Sign in · GoTry')
@section('content')
@php($activityContext = filter_var(old('activity', request()->query('activity')), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]))
<section class="panel account">
    <h1>{{ $mode === 'register' ? 'Create your account' : 'Welcome back' }}</h1>
    <p>{{ $mode === 'register' ? 'Join as a parent or activity provider.' : 'Sign in to your GoTry account.' }}</p>
    @if ($errors->any())
        <div class="error" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <form method="post" action="{{ $mode === 'register' ? route('register') : url('/login') }}">
        @csrf
        @if($activityContext)
            <input type="hidden" name="activity" value="{{ $activityContext }}">
        @endif
        @if ($mode === 'register')
            <label for="name">Full name</label>
            <input id="name" name="name" value="{{ old('name') }}" maxlength="100" autocomplete="name" required>
            <label for="role">I am a</label>
            <select id="role" name="role" required>
                <option value="parent" @selected(old('role', 'parent') === 'parent')>Parent</option>
                <option value="provider" @selected(old('role') === 'provider')>Activity provider</option>
            </select>
        @endif
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" maxlength="254" autocomplete="email" required>
        <label for="password">Password</label>
        <input id="password" name="password" type="password" maxlength="128" @if($mode === 'register') minlength="12" @endif autocomplete="{{ $mode === 'register' ? 'new-password' : 'current-password' }}" required>
        @if ($mode === 'register')
            <small>Use at least 12 characters.</small>
            <label for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" minlength="12" maxlength="128" autocomplete="new-password" required>
        @endif
        <button type="submit">{{ $mode === 'register' ? 'Create account' : 'Sign in' }}</button>
    </form>
    <p>@if ($mode === 'register')Already registered? <a href="{{ route('login', $activityContext ? ['activity' => $activityContext] : []) }}">Sign in</a>@else New to GoTry? <a href="{{ route('register', $activityContext ? ['activity' => $activityContext] : []) }}">Create an account</a>@endif</p>
</section>
@endsection
