@extends('layouts.app')
@section('title','Users')
@section('subtitle','Who can sign in and what they can do')
@section('content')
<div class="card mb-3"><div class="card-body"><form class="row g-2" method="GET">
  <div class="col-md-5"><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Search name or email"></div>
  <div class="col-md-7 d-flex gap-2"><button class="btn btn-outline-primary">Search</button><a href="{{ route('users.create') }}" class="btn btn-primary ms-auto"><i class="bi bi-plus-lg"></i> New user</a></div>
</form></div></div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead><tr><th>User</th><th>Roles</th><th>Last login</th><th>Status</th><th></th></tr></thead><tbody>
  @foreach($users as $u)
    <tr><td><div class="fw-semibold">{{ $u->name }}</div><div class="small text-muted">{{ $u->email }}</div></td>
      <td>@forelse($u->roles as $r)<span class="badge text-bg-{{ $r->slug==='super-admin'?'dark':'secondary' }} me-1">{{ $r->name }}</span>@empty<span class="text-muted">—</span>@endforelse</td>
      <td class="text-nowrap small text-muted">{{ $u->last_login_at?->diffForHumans() ?? 'never' }}</td>
      <td><span class="badge text-bg-{{ $u->is_active?'success':'secondary' }}">{{ $u->is_active?'Active':'Inactive' }}</span></td>
      <td class="text-end text-nowrap"><a href="{{ route('users.edit',$u) }}" class="btn btn-sm btn-outline-primary">Edit</a>
        @if($u->id!==auth()->id())<form method="POST" action="{{ route('users.destroy',$u) }}" class="d-inline" onsubmit="return confirm('Delete user?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form>@endif</td></tr>
  @endforeach</tbody></table></div>
  @if($users->hasPages())<div class="card-footer">{{ $users->links() }}</div>@endif
</div>
@endsection
