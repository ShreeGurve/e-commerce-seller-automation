@extends('layouts.app')
@section('title','Edit listing')
@section('subtitle',$listing->product->sku.' · '.$listing->marketplace->name.' · saving creates version '.(($listing->current->version_no ?? 0)+1))
@section('content')
<div class="card"><div class="card-body"><form method="POST" action="{{ route('listings.update',$listing) }}">@csrf @method('PUT') @include('listings._form')
  <div class="mt-4"><button class="btn btn-primary">Save as new version</button> <a href="{{ route('listings.show',$listing) }}" class="btn btn-link">Cancel</a></div></form></div></div>
@endsection
