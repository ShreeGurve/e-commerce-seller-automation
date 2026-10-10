@extends('layouts.app')
@section('title','Edit '.$purchase->po_number)
@section('content')
<div class="card"><div class="card-body"><form method="POST" action="{{ route('purchases.update',$purchase) }}">@csrf @method('PUT')
  @include('purchases._form')
  <div class="mt-4"><button class="btn btn-primary">Save changes</button> <a href="{{ route('purchases.show',$purchase) }}" class="btn btn-link">Cancel</a></div>
</form></div></div>
@endsection
