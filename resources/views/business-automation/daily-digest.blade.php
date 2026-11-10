@extends('layouts.app')
@section('title', 'Daily Executive Digest')
@section('content')
<div class="container-fluid py-3">
 <div class="d-flex flex-wrap justify-content-between align-items-center mb-3"><div><h1 class="h3 mb-1">Daily Executive Digest</h1><p class="text-muted mb-0">Internal daily snapshot. No email or external notification is sent.</p></div><form method="get" class="d-flex gap-2"><input class="form-control" type="date" name="date" value="{{ $date }}"><button class="btn btn-primary">View</button></form></div>
 <div class="row g-3 mb-4">
 @foreach([['Sales value',$actuals['sales_value'],true],['Eligible orders',$actuals['orders'],false],['Delivered / closed',$actuals['fulfilled_orders'],false],['Approved requisitions',$actuals['approved_requisitions'],false],['Supplier payments',$actuals['supplier_payments'],true],['Open alerts',$openCount,false]] as $card)
 <div class="col-6 col-xl-2"><div class="card h-100"><div class="card-body"><div class="text-muted small">{{ $card[0] }}</div><div class="h4 mb-0">{{ $card[2] ? number_format((float)$card[1],2) : number_format((float)$card[1]) }}</div></div></div></div>
 @endforeach
 </div>
 <div class="card"><div class="card-header fw-semibold">Alerts created on {{ $date }}</div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Severity</th><th>Alert</th><th>Details</th><th>Status</th><th>Created</th></tr></thead><tbody>
 @forelse($events as $event)<tr><td>{{ $event->severity }}</td><td>{{ $event->title }}</td><td>{{ $event->message }}</td><td>{{ $event->status }}</td><td>{{ optional($event->created_at)->format('H:i') }}</td></tr>@empty<tr><td colspan="5" class="text-center text-muted py-4">No alerts were created on this date.</td></tr>@endforelse
 </tbody></table></div></div>
</div>
@endsection
