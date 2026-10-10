@extends('layouts.app')
@section('title','New user')
@section('content')
<div class="card"><div class="card-body"><form method="POST" action="{{ route('users.store') }}">@csrf @include('users._form')
  <div class="mt-4"><button class="btn btn-primary">Create user</button> <a href="{{ route('users.index') }}" class="btn btn-link">Cancel</a></div></form></div></div>
@endsection
