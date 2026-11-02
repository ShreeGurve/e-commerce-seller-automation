<!doctype html><html lang="en"><head><meta charset="utf-8"><title>{{ $data['title'] }} · SHREE GURVE</title>
<style>body{font:12px/1.4 Arial,Helvetica,sans-serif;color:#222;margin:18px}h1{font-size:18px;margin:0}.sub{color:#666;margin:2px 0 12px}table{border-collapse:collapse;width:100%}th,td{border:1px solid #bbb;padding:3px 6px;text-align:left}
th{background:#eee}td.n,th.n{text-align:right}tfoot td{font-weight:bold;background:#f6f6f6}.note{color:#666;margin-top:8px}@media print{.no-print{display:none}body{margin:0}}</style></head>
<body onload="window.print()">
<h1>SHREE GURVE · {{ $data['title'] }}</h1>
<div class="sub">@if(in_array('date',$data['filters'],true)){{ \Carbon\Carbon::parse($f['from'])->format('d M Y') }} to {{ \Carbon\Carbon::parse($f['to'])->format('d M Y') }} · @endif{{ $mp ?? 'All channels' }} · printed {{ now()->format('d M Y H:i') }} by {{ $by }}</div>
@php $rows=$data['rows']; $tableClass='print'; @endphp
@include('reports._table')
@if($data['note'])<div class="note">{{ $data['note'] }}</div>@endif
<p class="no-print"><button onclick="window.print()">Print / Save as PDF</button></p>
<script>document.querySelectorAll('td.text-end,th.text-end').forEach(e=>e.classList.add('n'));</script>
</body></html>
