@extends('layouts.app')
@section('title','Edit '.$claim->claim_number)
@section('subtitle',$claim->status==='DRAFT'?'Draft: everything can change':'Amounts and type are locked once filed. Notes, deadline and portal id can still change.')
@section('content')
<div class="card"><div class="card-body"><form method="POST" action="{{ route('claims.update',$claim) }}">@csrf @method('PUT') @php $order=null; $return=null; $marketplaces=collect(); @endphp @include('claims._form')
  <div class="mt-4"><button class="btn btn-primary">Save changes</button> <a href="{{ route('claims.show',$claim) }}" class="btn btn-link">Cancel</a></div></form></div></div>
@endsection
