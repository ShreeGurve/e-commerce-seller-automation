@extends('layouts.app')
@section('title',$k->keyword)
@section('subtitle',$k->product->sku.' · '.$k->marketplace->name)
@section('content')
<div class="row g-3">
  <div class="col-lg-6"><div class="card"><div class="card-header">Details</div><div class="card-body"><form method="POST" action="{{ route('keywords.update',$k) }}" class="row g-3">@csrf @method('PUT')
    <div class="col-md-6"><label class="form-label">Type</label><select name="keyword_type" class="form-select">@foreach(\App\Models\Keyword::TYPES as $key=>$l)<option value="{{ $key }}" @selected(old('keyword_type',$k->keyword_type)===$key)>{{ $l }}</option>@endforeach</select></div>
    <div class="col-md-6"><label class="form-label">Search volume</label><input type="number" min="0" name="search_volume" value="{{ old('search_volume',$k->search_volume) }}" class="form-control"></div>
    <div class="col-md-4"><label class="form-label">Competition</label><select name="competition" class="form-select"><option value="">—</option>@foreach(\App\Models\Keyword::COMPETITION as $key=>$l)<option value="{{ $key }}" @selected(old('competition',$k->competition)===$key)>{{ $l }}</option>@endforeach</select></div>
    <div class="col-md-4"><label class="form-label">Relevance (0-100)</label><input type="number" min="0" max="100" name="relevance" value="{{ old('relevance',$k->relevance) }}" class="form-control"></div>
    <div class="col-md-4"><label class="form-label">Source</label><input name="source" value="{{ old('source',$k->source) }}" class="form-control"></div>
    <div class="col-md-6"><label class="form-label">Group</label><input name="group_name" value="{{ old('group_name',$k->group->name ?? '') }}" class="form-control" list="grp2"><datalist id="grp2">@foreach($groups as $g)<option value="{{ $g->name }}">@endforeach</datalist></div>
    <div class="col-md-6"><label class="form-label">Notes</label><input name="notes" value="{{ old('notes',$k->notes) }}" class="form-control"></div>
    <div class="col-12"><button class="btn btn-primary">Save</button> <a href="{{ route('keywords.index') }}" class="btn btn-link">Back</a></div></form>
    <form method="POST" action="{{ route('keywords.destroy',$k) }}" class="mt-2" onsubmit="return confirm('Delete this keyword and its ranking history?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete keyword</button></form></div></div></div>
  <div class="col-lg-6"><div class="card"><div class="card-header">Ranking history</div><div class="card-body">
    <form method="POST" action="{{ route('keywords.rank',$k) }}" class="row g-2 mb-3">@csrf
      <div class="col-4"><input type="number" min="1" name="rank" class="form-control" placeholder="Rank" required></div><div class="col-4"><input type="date" name="checked_on" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" class="form-control" required></div>
      <div class="col-4"><select name="rank_type" class="form-select"><option value="ORGANIC">Organic</option><option value="SPONSORED">Sponsored</option></select></div><div class="col-8"><input name="source" class="form-control" placeholder="Source (tool, manual)"></div><div class="col-4"><button class="btn btn-outline-primary w-100">Add</button></div></form>
    @if($history->count()>1)<div style="height:160px" class="mb-3"><canvas id="rk"></canvas></div>@endif
    <table class="table table-sm mb-0"><tbody>@forelse($history as $h)<tr><td>{{ $h->checked_on->format('d M Y') }}</td><td>{{ ucfirst(strtolower($h->rank_type)) }}</td><td class="text-end fw-bold">#{{ $h->rank }}</td></tr>@empty<tr><td class="text-muted">No rankings recorded yet.</td></tr>@endforelse</tbody></table></div></div></div>
</div>
@if($history->count()>1)@push('scripts')<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script><script>
const H=@json($history->where('rank_type','ORGANIC')->sortBy('checked_on')->values()->map(fn($h)=>['d'=>$h->checked_on->format('d M'),'r'=>$h->rank]));
new Chart(document.getElementById('rk'),{type:'line',data:{labels:H.map(x=>x.d),datasets:[{label:'Organic rank (lower is better)',data:H.map(x=>x.r),borderColor:'#3874ff',tension:.25}]},options:{maintainAspectRatio:false,scales:{y:{reverse:true,beginAtZero:false}}}});
</script>@endpush@endif
@endsection
