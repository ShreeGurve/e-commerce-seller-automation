@extends('layouts.app')
@section('title','My profile')
@section('subtitle',$user->email)
@section('content')
<div class="row g-3">
  <div class="col-lg-6"><div class="card"><div class="card-header">Details</div><div class="card-body">
    <form method="POST" action="{{ route('profile.update') }}">@csrf @method('PUT')
      <div class="mb-3"><label class="form-label">Name</label><input name="name" value="{{ old('name',$user->name) }}" class="form-control" required></div>
      <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" value="{{ old('email',$user->email) }}" class="form-control" required></div>
      <div class="mb-3"><label class="form-label">Phone</label><input name="phone" value="{{ old('phone',$user->phone) }}" class="form-control"></div>
      <div class="mb-3 small text-muted">Roles: {{ $user->roles->pluck('name')->join(', ') ?: '—' }}</div>
      <button class="btn btn-primary">Save</button>
    </form></div></div></div>
  <div class="col-lg-6"><div class="card"><div class="card-header">Change password</div><div class="card-body">
    <form method="POST" action="{{ route('profile.password') }}">@csrf @method('PUT')
      <div class="mb-3"><label class="form-label">Current password</label><input type="password" name="current_password" class="form-control" required></div>
      <div class="mb-3"><label class="form-label">New password</label><input type="password" name="password" class="form-control" required><div class="form-text">Min 8 characters, letters and numbers.</div></div>
      <div class="mb-3"><label class="form-label">Confirm new password</label><input type="password" name="password_confirmation" class="form-control" required></div>
      <button class="btn btn-primary">Change password</button>
    </form></div></div></div>
</div>
@endsection
