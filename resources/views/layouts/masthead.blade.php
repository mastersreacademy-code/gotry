<header class="gotry-masthead">
    <a class="gotry-masthead-logo" href="{{ route('home') }}">
        <img src="{{ $mastheadLogo ?? asset('images/gotry-activity-logo.webp') }}" alt="GoTry home" width="146" height="97">
    </a>
    <div class="gotry-masthead-identity">
        <strong>{{ $mastheadTitle ?? 'GoTry' }}</strong>
        <span>{{ $mastheadSubtitle ?? 'Try before you enrol' }}</span>
    </div>
    <div class="gotry-masthead-actions">
        @isset($mastheadStatus)
            <span class="gotry-masthead-status gotry-masthead-status-{{ $mastheadState }}">{{ $mastheadStatus }}</span>
        @endisset
        <details class="gotry-menu">
            <summary aria-label="{{ auth()->check() ? 'Account menu' : 'Navigation menu' }}" title="{{ auth()->check() ? 'Account menu' : 'Navigation menu' }}">
                @auth
                    @include('layouts.navigation-icon', ['icon' => 'user'])
                @else
                    @include('layouts.navigation-icon', ['icon' => 'menu'])
                @endauth
            </summary>
            @include('layouts.navigation', ['showIcons' => true])
        </details>
    </div>
</header>
