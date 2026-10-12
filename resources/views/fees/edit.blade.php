@extends('layouts.app')
@section('title','Edit fee rule')
@section('content')
<div class="card"><div class="card-body"><form method="POST" action="{{ route('fees.update',$rule) }}">@csrf @method('PUT') @include('fees._form')
  <div class="mt-4"><button class="btn btn-primary">Save changes</button> <a href="{{ route('fees.index') }}" class="btn btn-link">Cancel</a></div></form></div></div>
@endsection
