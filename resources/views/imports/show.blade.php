@extends('layouts.app')
@section('title',$import->file_name)
@section('subtitle',$import->marketplace->name.' · '.$import->typeLabel().' · '.$import->created_at->format('d M Y H:i').' · '.($import->uploader->name ?? '—'))
@section('content')
@if($import->status==='IMPORTED') @include('imports._steps',['step'=>5]) @endif
@if($import->status==='FAILED')<div class="alert alert-danger">Import stopped: {{ $import->error_message }}. Records created before the stop are kept. You can run it again safely: existing ones are skipped.
  <a class="alert-link" href="{{ route('imports.preview',$import) }}">Check &amp; re-run</a></div>@endif
@if(in_array($import->status,['UPLOADED','MAPPED']))<div class="alert alert-warning">Not imported yet. <a class="alert-link" href="{{ route('imports.map',$import) }}">Continue</a></div>@endif
<div class="row g-3 mb-3">
  @foreach([['Rows',$import->total_rows,'t-blue','bi-file-earmark-text'],['Imported OK',$import->ok_rows,'t-green','bi-check2-circle'],['Duplicates skipped',$import->duplicate_rows,'t-orange','bi-copy'],['Errors',$import->failed_rows,'t-red','bi-x-octagon']] as [$l,$v,$c,$i])
  <div class="col-6 col-xl-3"><div class="card"><div class="card-body d-flex align-items-center gap-3"><div class="sg-tile {{ $c }}"><i class="bi {{ $i }}"></i></div><div><div class="small text-muted">{{ $l }}</div><div class="fw-bold fs-4">{{ $v }}</div></div></div></div></div>
  @endforeach
</div>
@if($import->status==='IMPORTED')
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
  @foreach(['problems'=>'Problems','ERROR'=>'Errors','DUPLICATE'=>'Duplicates','OK'=>'Imported','all'=>'All rows'] as $k=>$l)
    <a href="{{ route('imports.show',[$import,'rows'=>$k]) }}" class="btn btn-sm btn-{{ $filter===$k?'primary':'outline-secondary' }}">{{ $l }}</a>@endforeach
  @if($import->failed_rows+$import->duplicate_rows>0)<a href="{{ route('imports.errors',$import) }}" class="btn btn-sm btn-outline-danger ms-auto"><i class="bi bi-download"></i> Download error report (CSV)</a>@endif
</div>
<div class="card"><div class="table-responsive"><table class="table table-sm align-middle mb-0">
  <thead><tr><th style="width:80px">Row</th><th style="width:110px">Result</th><th>Details</th><th style="width:140px">Record</th></tr></thead><tbody>
  @forelse($rows as $r)
    <tr><td>{{ $r->row_number }}</td><td><span class="badge text-bg-{{ ['OK'=>'success','ERROR'=>'danger','DUPLICATE'=>'warning'][$r->status] }}">{{ ucfirst(strtolower($r->status)) }}</span></td>
      <td>{{ $r->message }}@if($r->raw)<div class="small text-muted text-truncate" style="max-width:520px">{{ collect($r->raw)->filter()->take(5)->map(fn($v,$k)=>"$k: $v")->join(' · ') }}</div>@endif</td>
      <td>@if($r->entity_type==='order')<a href="{{ route('orders.show',$r->entity_id) }}" class="text-decoration-none">Order #{{ $r->entity_id }}</a>
          @elseif($r->entity_type==='return')<a href="{{ route('returns.show',$r->entity_id) }}" class="text-decoration-none">Return #{{ $r->entity_id }}</a>
          @elseif($r->entity_type==='settlement')<a href="{{ route('settlements.show',$r->entity_id) }}" class="text-decoration-none">Settlement #{{ $r->entity_id }}</a>
          @elseif($r->entity_type==='mapping')<span class="text-muted">Mapping #{{ $r->entity_id }}</span>@endif</td></tr>
  @empty<tr><td colspan="4" class="text-center text-muted py-4">Nothing to show here.</td></tr>@endforelse
  </tbody></table></div>
  @if($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif
</div>
@endif
@endsection
