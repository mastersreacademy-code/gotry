@extends('layouts.app')
@section('content')
<section class="hero">
    <p class="eyebrow">A little discovery. A world of possibility.</p>
    <h1>Try before you <span>enrol.</span></h1>
    <p>Discover activities your child will love. Meet providers and explore an introductory trial before choosing regular lessons.</p>
    <a class="button" href="{{ route('activities.index') }}">Explore activities →</a>
    <p>New to GoTry? <a href="{{ route('register') }}">Create your account</a>.</p>
</section>
<div class="grid">
    <article><h2>For families</h2><p>Explore approved activities, register your child’s interest and manage registrations from your dashboard.</p></article>
    <article><h2>For providers</h2><p>List your activity, add trial and regular lesson details, and submit it for review.</p></article>
</div>
<p class="notice">Registering interest is free. It does not confirm a booking or collect payment.</p>
@endsection
