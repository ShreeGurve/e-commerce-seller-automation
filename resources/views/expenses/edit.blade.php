@extends('layouts.app')
@section('title','Edit expense')
@section('content')
<div class="card"><div class="card-body"><form method="POST" action="{{ route('expenses.update',$expense) }}" enctype="multipart/form-data">@csrf @method('PUT') @include('expenses._form')
  <div class="mt-4"><button class="btn btn-primary">Save changes</button> <a href="{{ route('expenses.index') }}" class="btn btn-link">Cancel</a></div></form></div></div>
@endsection
