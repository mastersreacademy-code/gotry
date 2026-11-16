<svg aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
        @if($icon === 'user')
        <circle cx="12" cy="8" r="3.25"/><path d="M5 20v-2a7 7 0 0 1 14 0v2"/>
        @elseif($icon === 'menu')
        <path d="M4 6h16M4 12h16M4 18h16"/>
        @elseif($icon === 'home')
        <path d="m3 10 9-7 9 7M5 9v11h5v-6h4v6h5V9"/>
        @elseif($icon === 'list')
        <path d="M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01"/>
        @elseif($icon === 'dashboard')
        <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
        @elseif($icon === 'bell')
        <path d="M18 8a6 6 0 0 0-12 0c0 8-3 8-3 10h18c0-2-3-2-3-10M10 21h4"/>
        @elseif($icon === 'logout')
        <path d="M12 4H4v16h8M10 12h11m-4-4 4 4-4 4"/>
        @elseif($icon === 'login')
        <path d="M12 4h8v16h-8M3 12h12m-4-4 4 4-4 4"/>
        @elseif($icon === 'add')
        <circle cx="9" cy="7" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2m4-12v6m-3-3h6"/>
    @endif
</svg>
