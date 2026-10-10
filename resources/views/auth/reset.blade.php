@extends('layouts.auth')
@section('title','Reset password')
@section('content')
<h4 class="fw-bold mb-3">Set a new password</h4>
<form method="POST" action="{{ route('password.update') }}">@csrf
  <input type="hidden" name="token" value="{{ $token }}">
  <div class="mb-3"><label class="form-label fw-semibold">Email</label><input type="email" name="email" value="{{ old('email',$email) }}" class="form-control form-control-lg" required></div>
  <div class="mb-3"><label class="form-label fw-semibold">New password</label><input type="password" name="password" class="form-control form-control-lg" required><div class="form-text">Min 8 characters, letters and numbers.</div></div>
  <div class="mb-4"><label class="form-label fw-semibold">Confirm password</label><input type="password" name="password_confirmation" class="form-control form-control-lg" required></div>
  <button class="btn btn-primary btn-lg w-100">Reset password</button>
</form>
@endsection
