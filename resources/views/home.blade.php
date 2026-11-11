@extends('layouts.app')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/discovery.css') }}">
@endpush
@section('content')
<div class="discovery-ui">
    <section class="discovery-hero">
        <div>
            <h1>Try before you <em>enrol.</em></h1>
            <p>Find a free introductory activity for your child and get to know the provider before choosing regular lessons.</p>
            <a class="button" href="{{ route('activities.index') }}">Explore activities →</a>
        </div>
        <div class="discovery-guide">
            <h2>How trials work</h2>
            <ol>
                <li><strong>Find a trial</strong><span>Check the age range, venue and session times.</span></li>
                <li><strong>Register interest</strong><span>Join the waitlist if the session is full.</span></li>
                <li><strong>Check your dashboard</strong><span>Follow your registration and respond to seat offers.</span></li>
            </ol>
        </div>
    </section>
    @guest
        <section class="panel discovery-provider">
            <div><h2>Run activities for children?</h2><p>Create a provider account to submit a trial for review.</p></div>
            <a class="button secondary" href="{{ route('register') }}">Create an account →</a>
        </section>
    @endguest
</div>
@endsection
