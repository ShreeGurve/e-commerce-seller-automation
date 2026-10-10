@extends('layouts.app')
@section('title',$title)
@section('subtitle','This module is part of the roadmap')
@section('content')
<div class="card"><div class="card-body text-center py-5">
  <div class="sg-tile t-blue mx-auto mb-3" style="width:64px;height:64px;font-size:1.8rem"><i class="bi bi-tools"></i></div>
  <h4 class="fw-bold">{{ $title }}</h4>
  @if(strtolower($title) === 'approvals')
    <div class="d-flex justify-content-center mb-3"><span class="badge rounded-pill text-bg-light border px-3 py-2" style="color:#68738a!important"><i class="bi bi-bell me-1"></i>Coming soon</span></div>
    <p class="text-muted mb-3">Approvals is planned for <b>{{ $phase }}</b>. This module is not available yet.</p>
    <p class="small text-muted mb-3"><i class="bi bi-info-circle me-1"></i>Check back in a future update when approval workflows are released.</p>
  @else
    <p class="text-muted mb-3">Scheduled for <b>{{ $phase }}</b> of the development plan.</p>
  @endif
  <a href="{{ route('dashboard') }}" class="btn btn-primary">Back to dashboard</a>
</div></div>
@endsection
