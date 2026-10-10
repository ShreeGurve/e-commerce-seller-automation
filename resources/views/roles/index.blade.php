@extends('layouts.app')
@section('title','Roles & Permissions')
@section('subtitle','Tick what each role may do. Super Admin always has everything.')
@section('content')
<div class="row g-3 mb-3">
  <div class="col-lg-5"><div class="card"><div class="card-body"><form method="POST" action="{{ route('roles.store') }}" class="d-flex gap-2">@csrf
    <input name="name" class="form-control" placeholder="New role name" required><button class="btn btn-outline-primary text-nowrap">Add role</button></form></div></div></div>
</div>
<form method="POST" action="{{ route('roles.update') }}">@csrf @method('PUT')
<div class="card"><div class="table-responsive"><table class="table table-sm align-middle mb-0">
  <thead><tr><th style="min-width:200px">Permission</th>
    @foreach($roles as $r)<th class="text-center text-nowrap">{{ $r->name }}<div class="fw-normal text-muted" style="text-transform:none;letter-spacing:0">{{ $r->users_count }} user(s)
      @unless($r->is_system || $r->users_count)<button type="submit" form="del{{ $r->id }}" class="btn btn-link btn-sm text-danger p-0 ms-1" onclick="return confirm('Delete role?')"><i class="bi bi-trash"></i></button>@endunless</div></th>@endforeach</tr></thead>
  <tbody>
  @foreach($permissions as $module => $perms)
    <tr><td colspan="{{ $roles->count()+1 }}" class="fw-bold pt-3" style="color:#3874ff">{{ ucfirst($module) }}</td></tr>
    @foreach($perms as $p)
      <tr><td class="text-nowrap">{{ $p->name }}</td>
        @foreach($roles as $r)<td class="text-center">
          @if($r->slug==='super-admin')<input class="form-check-input" type="checkbox" checked disabled>
          @else<input class="form-check-input" type="checkbox" name="perm[{{ $r->id }}][]" value="{{ $p->id }}" @checked(in_array($p->id,$matrix[$r->id]))>@endif</td>@endforeach</tr>
    @endforeach
  @endforeach</tbody></table></div>
  <div class="card-footer"><button class="btn btn-primary">Save permissions</button></div></div>
</form>
@foreach($roles as $r)@unless($r->is_system || $r->users_count)<form id="del{{ $r->id }}" method="POST" action="{{ route('roles.destroy',$r) }}">@csrf @method('DELETE')</form>@endunless @endforeach
@endsection
