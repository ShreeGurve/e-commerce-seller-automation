@php $st=$claim->deadlineState(); $d=$claim->daysLeft(); @endphp
@if(!$claim->deadline)<span class="text-muted">—</span>
@elseif($st==='EXPIRED')<span class="badge text-bg-danger">Expired {{ abs($d) }}d ago</span> <span class="small text-muted">{{ $claim->deadline->format('d M') }}</span>
@elseif($st==='URGENT')<span class="badge text-bg-danger">URGENT · {{ $d===0?'today':$d.'d left' }}</span> <span class="small text-muted">{{ $claim->deadline->format('d M') }}</span>
@elseif($st==='WARNING')<span class="badge text-bg-warning">{{ $d }}d left</span> <span class="small text-muted">{{ $claim->deadline->format('d M') }}</span>
@else<span class="small {{ $st===null?'text-muted':'' }}">{{ $claim->deadline->format('d M Y') }}</span>@endif
