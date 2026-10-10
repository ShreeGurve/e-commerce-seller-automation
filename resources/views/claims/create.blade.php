@extends('layouts.app')
@section('title','New claim')
@section('subtitle',$return ? 'Pre-filled from return '.$return->return_number : 'Track a claim you file on the marketplace portal')
@section('content')
<div class="card"><div class="card-body"><form method="POST" action="{{ route('claims.store') }}">@csrf @include('claims._form')
  <div class="mt-4"><button class="btn btn-primary">Save draft</button> <a href="{{ route('claims.index') }}" class="btn btn-link">Cancel</a></div></form></div></div>
@endsection
