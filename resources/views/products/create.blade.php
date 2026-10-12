@extends('layouts.app')
@section('title','New product')
@section('content')
<div class="card"><div class="card-body">
  <form method="POST" action="{{ route('products.store') }}">@csrf
    @include('products._form')
    <div class="mt-4"><button class="btn btn-primary">Create product</button> <a href="{{ route('products.index') }}" class="btn btn-link">Cancel</a></div>
  </form></div></div>
@endsection
