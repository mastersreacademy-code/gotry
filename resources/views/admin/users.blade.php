@extends('layouts.app')
@section('title','Accounts · GoTry')
@section('content')
@include('admin.nav')<h1>Accounts</h1>
<form method="get" class="admin-filters">
    <label>Search name/email<input name="q" value="{{ request('q') }}" maxlength="160"></label>
    <label>Role<select name="role"><option value="">All roles</option>@foreach(['admin'=>'Super admin','provider'=>'Provider','parent'=>'Parent'] as $value=>$label)<option value="{{ $value }}" @selected(request('role')===$value)>{{ $label }}</option>@endforeach</select></label>
    <label>Access<select name="access"><option value="">All accounts</option><option value="active" @selected(request('access')==='active')>Active</option><option value="suspended" @selected(request('access')==='suspended')>Suspended</option></select></label><button>Filter</button><a href="{{ route('admin.users') }}">Clear</a>
</form>
<p>Suspension revokes access, including existing sessions. Roles are assigned at registration; existing activity and registration ownership is preserved.</p>
<ul class="admin-records">
@forelse($users as $user)
    <li><article class="admin-record">
        <div>
            <h2>{{ $user->name }} @if($user->id === auth()->id())<span class="admin-you">(you)</span>@endif</h2>
            <p>{{ $user->email }}</p>
        </div>
        <div class="admin-record-meta">
            <span>{{ $user->role === 'admin' ? 'Super admin' : ucfirst($user->role) }}</span>
            <span @class(['status', 'admin-active' => $user->is_active, 'status-rejected' => !$user->is_active])>{{ $user->is_active ? 'Active' : 'Suspended' }}</span>
        </div>
        @if($user->id === auth()->id())
            <p>Your admin access is protected.</p>
        @else
            <details class="admin-account-action">
                <summary>Manage access</summary>
                <form method="post" action="{{ route('admin.access', $user) }}">
                    @csrf
                    <input type="hidden" name="expected_version" value="{{ $user->access_version }}">
                    <input type="hidden" name="action" value="{{ $user->is_active ? 'suspend' : 'activate' }}">
                    <label for="reason-{{ $user->id }}">Reason</label>
                    <input id="reason-{{ $user->id }}" name="reason" maxlength="1000" required>
                    <button type="submit" class="{{ $user->is_active ? 'admin-danger' : 'secondary' }}">{{ $user->is_active ? 'Suspend access' : 'Activate access' }}</button>
                </form>
            </details>
        @endif
    </article></li>
@empty
    <li class="admin-empty">No matching accounts.</li>
@endforelse
</ul>
{{ $users->links() }}
@endsection
