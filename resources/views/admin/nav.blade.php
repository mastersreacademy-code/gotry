<nav class="admin-nav" aria-label="Super admin navigation">
@foreach(['admin.dashboard'=>'Overview','admin.activities'=>'Activities','admin.users'=>'Accounts','admin.audit'=>'Audit history','admin.guide'=>'Guide'] as $route=>$label)
<a href="{{ route($route) }}" @if(request()->routeIs($route) || ($route==='admin.activities' && request()->routeIs('admin.activity'))) aria-current="page" @endif>{{ $label }}</a>
@endforeach
</nav>
@if(session('status'))<p class="notice" role="status">{{ session('status') }}</p>@endif
@if($errors->any())<div class="error" role="alert">{{ $errors->first() }}</div>@endif
