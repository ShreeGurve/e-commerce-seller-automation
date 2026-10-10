@extends('layouts.app')
@section('title','Edit user')
@section('subtitle',$user->email)
@section('content')
<div class="card"><div class="card-body"><form method="POST" action="{{ route('users.update',$user) }}">@csrf @method('PUT') @include('users._form')
  <div class="mt-4"><button class="btn btn-primary">Save changes</button> <a href="{{ route('users.index') }}" class="btn btn-link">Cancel</a></div></form></div></div>
@endsection
