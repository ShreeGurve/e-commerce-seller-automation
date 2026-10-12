@extends('layouts.auth')
@section('title','Forgot password')
@section('content')
<h4 class="fw-bold mb-1">Forgot password?</h4><p class="text-secondary mb-4">Enter your email and we will send a reset link.</p>
<form method="POST" action="{{ route('password.email') }}">@csrf
  <div class="mb-3"><label class="form-label fw-semibold">Email</label><input type="email" name="email" value="{{ old('email') }}" class="form-control form-control-lg" required autofocus></div>
  <button class="btn btn-primary btn-lg w-100">Send reset link</button>
</form>
@endsection
