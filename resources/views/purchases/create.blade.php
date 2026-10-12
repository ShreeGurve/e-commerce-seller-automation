@extends('layouts.app')
@section('title','New purchase')
@section('subtitle','Receiving a purchase adds the stock to inventory automatically')
@section('content')
<div class="card"><div class="card-body"><form method="POST" action="{{ route('purchases.store') }}">@csrf
  @include('purchases._form')
  <div class="mt-4"><button class="btn btn-primary">Save purchase</button> <a href="{{ route('purchases.index') }}" class="btn btn-link">Cancel</a></div>
</form></div></div>
@endsection
