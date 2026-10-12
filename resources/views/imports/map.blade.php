@extends('layouts.app')
@section('title','Map columns')
@section('subtitle',$import->file_name.' · '.$import->marketplace->name.' · '.$import->typeLabel().' · '.$import->total_rows.' rows')
@section('content')
@include('imports._steps',['step'=>2])
<form method="POST" action="{{ route('imports.map.save',$import) }}">@csrf
<div class="card mb-3"><div class="card-header">Tell us which file column holds each value</div><div class="table-responsive"><table class="table align-middle mb-0">
  <thead><tr><th style="width:240px">Our field</th><th style="width:320px">Column in your file</th><th>Example from file</th></tr></thead><tbody>
  @foreach($import->fields() as $field=>[$label,$required])
    <tr><td class="fw-semibold">{{ $label }} @if($required)<span class="badge text-bg-danger">required</span>@else<span class="badge text-bg-light border">optional</span>@endif</td>
      <td><select name="map[{{ $field }}]" class="form-select" data-field="{{ $field }}" {{ $required?'required':'' }}><option value="">{{ $required?'Select column…':'— not in file —' }}</option>
        @foreach($headers as $h)<option value="{{ $h }}" @selected(($map[$field] ?? null)===$h)>{{ $h }}</option>@endforeach</select></td>
      <td class="text-muted small ex" data-field="{{ $field }}"></td></tr>
  @endforeach</tbody></table></div></div>
<div class="card mb-3"><div class="card-header">First rows of your file</div><div class="table-responsive"><table class="table table-sm mb-0">
  <thead><tr>@foreach($headers as $h)<th class="text-nowrap">{{ $h }}</th>@endforeach</tr></thead><tbody>
  @foreach($sample as $r)<tr>@foreach($headers as $h)<td class="text-nowrap">{{ \Illuminate\Support\Str::limit($r[$h] ?? '',40) }}</td>@endforeach</tr>@endforeach</tbody></table></div></div>
<button class="btn btn-primary">Save mapping &amp; check file</button> <a href="{{ route('imports.create') }}" class="btn btn-link">Start over</a>
<span class="text-muted small ms-2">Your choice is remembered for next time.</span>
</form>
@push('scripts')<script>
const S=@json($sample);
function ex(){document.querySelectorAll('select[data-field]').forEach(s=>{const c=document.querySelector('.ex[data-field="'+s.dataset.field+'"]');c.textContent=s.value?S.map(r=>r[s.value]).filter(Boolean).slice(0,2).join('  |  '):'';});}
document.querySelectorAll('select[data-field]').forEach(s=>s.onchange=ex);ex();
</script>@endpush
@endsection
