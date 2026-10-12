@extends('layouts.app')
@section('title','Edit supplier')
@section('subtitle',$supplier->name)
@section('content')
<div class="card"><div class="card-body"><form method="POST" action="{{ route('suppliers.update',$supplier) }}">@csrf @method('PUT')
  @include('suppliers._form')
  <div class="mt-4"><button class="btn btn-primary">Save changes</button> <a href="{{ route('suppliers.index') }}" class="btn btn-link">Cancel</a></div>
</form></div></div>
@endsection
