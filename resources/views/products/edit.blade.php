@extends('layouts.app')
@section('title','Edit '.$product->sku)
@section('content')
<div class="alert alert-info small">Costs are changed from the product page → <b>Costs</b> tab, so every change keeps its history.</div>
<div class="card"><div class="card-body">
  <form method="POST" action="{{ route('products.update',$product) }}">@csrf @method('PUT')
    @include('products._form')
    <div class="mt-4"><button class="btn btn-primary">Save changes</button> <a href="{{ route('products.show',$product) }}" class="btn btn-link">Cancel</a></div>
  </form></div></div>
@endsection
