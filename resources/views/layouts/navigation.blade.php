<nav aria-label="Main navigation">
    <a href="{{ auth()->user()?->role === 'parent' ? route('parent.children') : route('home') }}">@if($showIcons ?? false)@include('layouts.navigation-icon', ['icon' => 'home']) @endif {{ auth()->user()?->role === 'parent' ? 'Add child' : 'Home' }}</a>
    @auth
        @if(auth()->user()->role === 'parent')
            <a href="{{ route('activities.index') }}">Explore activities</a>
            <a href="{{ route('notifications.index') }}">Notifications</a>
            <a href="{{ route('parent.dashboard') }}">@if($showIcons ?? false)@include('layouts.navigation-icon', ['icon' => 'list']) @endif My List</a>
        @else
        <a href="{{ route('dashboard') }}">@if($showIcons ?? false)@include('layouts.navigation-icon', ['icon' => 'dashboard']) @endif Dashboard</a>
        <a href="{{ route('notifications.index') }}">@if($showIcons ?? false)@include('layouts.navigation-icon', ['icon' => 'bell']) @endif Notifications</a>
        @endif
        <form method="post" action="{{ route('logout') }}">@csrf<button class="secondary" type="submit">@if($showIcons ?? false)@include('layouts.navigation-icon', ['icon' => 'logout']) @endif Sign out</button></form>
    @else
        <a href="{{ route('login') }}">@if($showIcons ?? false)@include('layouts.navigation-icon', ['icon' => 'login']) @endif Sign in</a>
        <a href="{{ route('register') }}">@if($showIcons ?? false)@include('layouts.navigation-icon', ['icon' => 'add']) @endif Create account</a>
    @endauth
</nav>
