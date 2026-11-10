@extends('layouts.app')
@section('content')
<section class="hero">
    <p class="eyebrow">A little discovery. A world of possibility.</p>
    <h1>Try before you <span>enrol.</span></h1>
    <p>Discover activities your child will love. Meet providers and explore an introductory trial before choosing regular lessons.</p>
    <a class="button" href="{{ route('register') }}">Create your account →</a>
</section>
<div class="grid">
    <article><h2>For families</h2><p>Find a new interest and a welcoming place to start.</p></article>
    <article><h2>For providers</h2><p>Introduce your activity to families in your community.</p></article>
</div>
<p class="notice">We are preparing GoTry for its new home. Account access is the first migration stage; activity listings and booking workflows are still being migrated.</p>
@endsection
