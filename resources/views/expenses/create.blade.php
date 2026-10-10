@extends('layouts.app')
@section('title','New expense')
@section('content')
<div class="card"><div class="card-body"><form method="POST" action="{{ route('expenses.store') }}" enctype="multipart/form-data">@csrf @include('expenses._form')
  <div class="mt-4"><button class="btn btn-primary">Save expense</button> <a href="{{ route('expenses.index') }}" class="btn btn-link">Cancel</a></div></form></div></div>
@endsection
