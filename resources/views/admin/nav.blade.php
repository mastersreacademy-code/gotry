@push('styles')
<link rel="stylesheet" href="{{ asset('css/admin.css') }}">
@endpush
<nav class="admin-nav admin-sidebar" aria-label="Super admin navigation">
@foreach(['admin.dashboard'=>'Overview','admin.activities'=>'Activities','admin.users'=>'Accounts','admin.audit'=>'Audit history','admin.guide'=>'Guide'] as $route=>$label)
<a href="{{ route($route) }}" @if(request()->routeIs($route) || ($route==='admin.activities' && request()->routeIs('admin.activity'))) aria-current="page" @endif>
    @include('layouts.navigation-icon', ['icon' => match($route) {
        'admin.dashboard' => 'dashboard',
        'admin.users' => 'user',
        default => 'list',
    }])
    <span>{{ $label }}</span>
</a>
@endforeach
</nav>
