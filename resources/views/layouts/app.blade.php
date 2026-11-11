<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title', 'GoTry — Try before you enrol')</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @stack('styles')
</head>
<body>
<a class="skip-link" href="#main-content">Skip to content</a>
<header>
    <a class="brand" href="{{ route('home') }}">Go<span>Try</span></a>
    <nav aria-label="Main navigation">
        <a href="{{ route('home') }}">Home</a>
        <a href="{{ route('activities.index') }}">Explore</a>
        @auth
            <a href="{{ route('dashboard') }}">Dashboard</a>
            <a href="{{ route('notifications.index') }}">Notifications</a>
            <form method="post" action="{{ route('logout') }}">@csrf<button class="secondary" type="submit">Sign out</button></form>
        @else
            <a href="{{ route('login') }}">Sign in</a>
            <a href="{{ route('register') }}">Create account</a>
        @endauth
    </nav>
</header>
<main id="main-content" tabindex="-1">@yield('content')</main>
<footer>GoTry · Development preview · No live bookings or payments</footer>
</body>
</html>
