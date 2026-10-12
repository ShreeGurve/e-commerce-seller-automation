@extends('layouts.app')
@section('title','Check before importing')
@section('subtitle',$import->file_name.' · '.$import->marketplace->name.' · '.$import->typeLabel())
@section('content')
@include('imports._steps',['step'=>3])
<div class="alert alert-secondary small">This is a dry run. <b>Nothing has been saved yet.</b></div>
<div class="row g-3 mb-3">
  @foreach([['Rows in file',$s['total'],'t-blue','bi-file-earmark-text'],['Will be created',$s['created'],'t-green','bi-check2-circle'],['Duplicates (skipped)',$s['duplicate'],'t-orange','bi-copy'],['Rows with errors',$s['error'],'t-red','bi-x-octagon']] as [$l,$v,$c,$i])
  <div class="col-6 col-xl-3"><div class="card"><div class="card-body d-flex align-items-center gap-3"><div class="sg-tile {{ $c }}"><i class="bi {{ $i }}"></i></div><div><div class="small text-muted">{{ $l }}</div><div class="fw-bold fs-4">{{ $v }}</div></div></div></div></div>
  @endforeach
</div>
@if($s['samples'])<div class="card mb-3"><div class="card-header">Examples of what will be created</div><ul class="list-group list-group-flush small">@foreach($s['samples'] as $t)<li class="list-group-item">{{ $t }}</li>@endforeach</ul></div>@endif
@if($s['issues'])<div class="card mb-3"><div class="card-header">Problems found (first {{ count($s['issues']) }})</div><div class="table-responsive"><table class="table table-sm align-middle mb-0">
  <thead><tr><th style="width:80px">Row</th><th style="width:110px">Type</th><th>What is wrong</th></tr></thead><tbody>
  @foreach($s['issues'] as $r)<tr><td>{{ $r['n'] }}</td><td><span class="badge text-bg-{{ $r['status']==='ERROR'?'danger':'warning' }}">{{ ucfirst(strtolower($r['status'])) }}</span></td><td>{{ $r['message'] }}</td></tr>@endforeach</tbody></table></div>
  <div class="card-footer small text-muted">Rows with errors are skipped. Everything else still imports. A full downloadable error report is available after the import.</div></div>@endif
<form method="POST" action="{{ route('imports.run',$import) }}" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Importing… please wait'">@csrf
  <button class="btn btn-primary btn-lg" {{ $s['created']<1 ? 'disabled' : '' }}>Import {{ $s['created'] }} {{ $import->data_type==='ORDERS'?'order(s)':($import->data_type==='RETURNS'?'return(s)':'mapping(s)') }}</button>
  <a href="{{ route('imports.map',$import) }}" class="btn btn-link">Change mapping</a>
</form>
@endsection
