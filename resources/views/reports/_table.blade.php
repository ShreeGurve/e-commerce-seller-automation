@php
  $fmt=function($v,$t){ if($v===null||$v==='') return '—'; return match($t){ 'int'=>number_format((float)$v), 'money'=>'₹'.number_format((float)$v,2), 'ratio'=>round((float)$v,2).'x', 'pct'=>round((float)$v,1).'%',
    'date'=>\Carbon\Carbon::parse($v)->format('d M Y'), default=>(string)$v }; };
  $num=fn($t)=>in_array($t,['int','money','ratio','pct'],true);
@endphp
<table class="table table-sm align-middle mb-0 {{ $tableClass ?? 'table-hover' }}">
  <thead><tr>@foreach($data['columns'] as [$k,$label,$t])<th class="{{ $num($t)?'text-end':'' }} text-nowrap">{{ $label }}</th>@endforeach</tr></thead>
  <tbody>
  @forelse($rows as $r)<tr>@foreach($data['columns'] as [$k,$label,$t]) @php $v=$r[$k] ?? null; @endphp
    <td class="{{ $num($t)?'text-end':'' }} {{ $num($t)&&is_numeric($v)&&$v<0&&in_array($k,['profit','margin','net_gst','change'],true)?'text-danger fw-semibold':'' }}">{{ $fmt($v,$t) }}</td>@endforeach</tr>
  @empty<tr><td colspan="{{ count($data['columns']) }}" class="text-center text-muted py-5">No data for these filters.</td></tr>@endforelse
  </tbody>
  @if($data['totals'] && count($rows))<tfoot><tr class="fw-bold">@foreach($data['columns'] as $i=>[$k,$label,$t])<td class="{{ $num($t)?'text-end':'' }}">{{ $i===0 ? 'TOTAL' : $fmt($data['totals'][$k] ?? null,$t) }}</td>@endforeach</tr></tfoot>@endif
</table>
