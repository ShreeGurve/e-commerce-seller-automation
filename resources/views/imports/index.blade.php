@extends('layouts.app')
@section('title','Imports')
@section('subtitle','Upload marketplace reports. Nothing is created twice.')
@section('content')
<div class="mb-3"><a href="{{ route('imports.create') }}" class="btn btn-primary"><i class="bi bi-cloud-upload"></i> New import</a></div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead><tr><th>File</th><th>Channel</th><th>Type</th><th class="text-end">Rows</th><th class="text-end">OK</th><th class="text-end">Duplicate</th><th class="text-end">Errors</th><th>Status</th><th>By</th></tr></thead><tbody>
  @forelse($imports as $i)
    <tr><td><a class="fw-semibold text-decoration-none" href="{{ route('imports.show',$i) }}">{{ $i->file_name }}</a><div class="small text-muted">{{ $i->created_at->format('d M Y H:i') }}</div></td>
      <td>{{ $i->marketplace->name }}</td><td>{{ $i->typeLabel() }}</td><td class="text-end">{{ $i->total_rows }}</td>
      <td class="text-end text-success">{{ $i->ok_rows }}</td><td class="text-end">{{ $i->duplicate_rows }}</td><td class="text-end {{ $i->failed_rows?'text-danger fw-bold':'' }}">{{ $i->failed_rows }}</td>
      <td><span class="badge text-bg-{{ ['UPLOADED'=>'secondary','MAPPED'=>'info','RUNNING'=>'warning','IMPORTED'=>'success','FAILED'=>'danger'][$i->status] }}">{{ ucfirst(strtolower($i->status)) }}</span></td>
      <td>{{ $i->uploader->name ?? '—' }}</td></tr>
  @empty<tr><td colspan="9" class="text-center text-muted py-5">No imports yet.</td></tr>@endforelse
  </tbody></table></div>
  @if($imports->hasPages())<div class="card-footer">{{ $imports->links() }}</div>@endif
</div>
@endsection
