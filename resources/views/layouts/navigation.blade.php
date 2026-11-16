<nav aria-label="Main navigation">
    <a href="{{ route('home') }}">@if($showIcons ?? false)@include('layouts.navigation-icon', ['icon' => 'home']) @endifHome</a>
    @auth
        @if(auth()->user()->role === 'parent')
            <a href="{{ route('parent.dashboard') }}">@if($showIcons ?? false)@include('layouts.navigation-icon', ['icon' => 'list']) @endifMy List</a>
        @else
        <a href="{{ route('dashboard') }}">@if($showIcons ?? false)@include('layouts.navigation-icon', ['icon' => 'dashboard']) @endifDashboard</a>
        <a href="{{ route('notifications.index') }}">@if($showIcons ?? false)@include('layouts.navigation-icon', ['icon' => 'bell']) @endifNotifications</a>
        @endif
        <form method="post" action="{{ route('logout') }}">@csrf<button class="secondary" type="submit">@if($showIcons ?? false)@include('layouts.navigation-icon', ['icon' => 'logout']) @endifSign out</button></form>
    @else
        <a href="{{ route('login') }}">@if($showIcons ?? false)@include('layouts.navigation-icon', ['icon' => 'login']) @endifSign in</a>
        <a href="{{ route('register') }}">@if($showIcons ?? false)@include('layouts.navigation-icon', ['icon' => 'add']) @endifCreate account</a>
    @endauth
</nav>
