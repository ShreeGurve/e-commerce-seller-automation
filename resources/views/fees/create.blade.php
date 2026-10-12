@extends('layouts.app')
@section('title','New fee rule')
@section('content')
<div class="card"><div class="card-body"><form method="POST" action="{{ route('fees.store') }}">@csrf @include('fees._form')
  <div class="mt-4"><button class="btn btn-primary">Save rule</button> <a href="{{ route('fees.index') }}" class="btn btn-link">Cancel</a></div></form></div></div>
@endsection
