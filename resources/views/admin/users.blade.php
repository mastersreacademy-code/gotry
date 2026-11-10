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
<div class="table-wrap"><table><thead><tr><th>Account</th><th>Role / access</th><th>Manage access</th></tr></thead><tbody>
@forelse($users as $user)<tr><td>{{ $user->name }}<br>{{ $user->email }}@if($user->id===auth()->id())<br><strong>You</strong>@endif</td><td>{{ $user->role==='admin' ? 'Super admin' : ucfirst($user->role) }}<br>{{ $user->is_active ? 'Active' : 'Suspended' }}</td><td>
@if($user->id===auth()->id())Your admin access is protected.
@else<form method="post" action="{{ route('admin.access',$user) }}">@csrf<input type="hidden" name="expected_version" value="{{ $user->access_version }}"><input type="hidden" name="action" value="{{ $user->is_active ? 'suspend' : 'activate' }}"><label for="reason-{{ $user->id }}">Reason</label><input id="reason-{{ $user->id }}" name="reason" maxlength="1000" required><button type="submit" class="secondary">{{ $user->is_active ? 'Suspend access' : 'Activate access' }}</button></form>@endif
</td></tr>@empty<tr><td colspan="3">No matching accounts.</td></tr>@endforelse
</tbody></table></div>{{ $users->links() }}
@endsection
