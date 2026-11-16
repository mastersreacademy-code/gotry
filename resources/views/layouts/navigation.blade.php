<nav aria-label="Main navigation">
    <a href="{{ route('home') }}">Home</a>
    @auth
        @if(auth()->user()->role === 'parent')
            <a href="{{ route('parent.dashboard') }}">My List</a>
        @else
        <a href="{{ route('dashboard') }}">Dashboard</a>
        <a href="{{ route('notifications.index') }}">Notifications</a>
        @endif
        <form method="post" action="{{ route('logout') }}">@csrf<button class="secondary" type="submit">Sign out</button></form>
    @else
        <a href="{{ route('login') }}">Sign in</a>
        <a href="{{ route('register') }}">Create account</a>
    @endauth
</nav>
