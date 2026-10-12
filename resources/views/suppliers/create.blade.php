@extends('layouts.app')
@section('title','New supplier')
@section('content')
<div class="card"><div class="card-body"><form method="POST" action="{{ route('suppliers.store') }}">@csrf
  @include('suppliers._form')
  <div class="mt-4"><button class="btn btn-primary">Save supplier</button> <a href="{{ route('suppliers.index') }}" class="btn btn-link">Cancel</a></div>
</form></div></div>
@endsection
