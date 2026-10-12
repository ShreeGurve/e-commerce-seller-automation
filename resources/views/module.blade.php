@extends('layouts.app')
@section('title',$title)
@section('subtitle','This module is part of the roadmap')
@section('content')
<div class="card"><div class="card-body text-center py-5">
  <div class="sg-tile t-blue mx-auto mb-3" style="width:64px;height:64px;font-size:1.8rem"><i class="bi bi-tools"></i></div>
  <h4 class="fw-bold">{{ $title }}</h4>
  <p class="text-muted mb-3">Scheduled for <b>{{ $phase }}</b> of the development plan.</p>
  <a href="{{ route('dashboard') }}" class="btn btn-primary">Back to dashboard</a>
</div></div>
@endsection
