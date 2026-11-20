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
@unless (View::hasSection('activity-masthead-navigation'))
@include('layouts.masthead')
@endunless
<main id="main-content" tabindex="-1">@yield('content')</main>
<footer>GoTry · Development preview · No live bookings or payments</footer>
</body>
</html>
