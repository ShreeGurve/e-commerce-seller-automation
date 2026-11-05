@extends('layouts.app')
@section('content')
<div class="container-fluid py-4">
 <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
  <div><h1 class="h3 mb-1">Operational Intelligence</h1><p class="text-muted mb-0">Work queue performance, approval turnaround and traceable activity.</p></div>
  <a class="btn btn-outline-primary" href="{{ route('governance.control-center') }}">Open Control Center</a>
 </div>
 <div class="card mb-4"><div class="card-body">
  <form method="GET" action="{{ route('operational-insights.index') }}" class="row g-2 align-items-end">
   <div class="col-md-3"><label class="form-label">From</label><input type="date" name="from" class="form-control" value="{{ $from }}"></div>
   <div class="col-md-3"><label class="form-label">To</label><input type="date" name="to" class="form-control" value="{{ $to }}"></div>
   <div class="col-md-2"><button class="btn btn-primary w-100">Apply filters</button></div>
   <div class="col-md-2"><a class="btn btn-outline-secondary w-100" href="{{ route('operational-insights.index') }}">Reset</a></div>
  </form>
 </div></div>
 <h2 class="h5 mb-3">Automation work queue</h2>
 <div class="row g-3 mb-4">
  @foreach([
   ['Alerts created',$eventStats->total ?? 0],
   ['Open alerts',$eventStats->open_count ?? 0],
   ['Resolved alerts',$eventStats->resolved_count ?? 0],
   ['Overdue alerts',$eventStats->overdue_count ?? 0],
   ['Avg. resolution hours',number_format((float)($eventStats->avg_resolution_hours ?? 0),1)],
  ] as $metric)
   <div class="col-6 col-lg"><div class="card h-100"><div class="card-body"><div class="small text-muted">{{ $metric[0] }}</div><div class="h3 mb-0">{{ $metric[1] }}</div></div></div></div>
  @endforeach
 </div>
 <div class="row g-3 mb-4">
  <div class="col-lg-5"><div class="card h-100"><div class="card-body"><h3 class="h6">Alerts by priority</h3>
   @forelse($byPriority as $row)<div class="d-flex justify-content-between border-bottom py-2"><span>{{ ucfirst($row->priority ?: 'normal') }}</span><strong>{{ $row->total }}</strong></div>@empty<p class="text-muted mb-0">No alert records in this period.</p>@endforelse
  </div></div></div>
  <div class="col-lg-7"><div class="card h-100"><div class="card-body"><h3 class="h6">Team workload</h3>
   <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Assignee</th><th>Alerts</th><th>Resolved</th></tr></thead><tbody>
   @forelse($byAssignee as $row)<tr><td>{{ $row->assignee_name }}</td><td>{{ $row->total }}</td><td>{{ $row->resolved }}</td></tr>@empty<tr><td colspan="3" class="text-muted">No assignments in this period.</td></tr>@endforelse
   </tbody></table></div>
  </div></div></div>
 </div>
 <h2 class="h5 mb-3">Approval workflow performance</h2>
 <div class="row g-3 mb-4">
  @foreach([
   ['Requests submitted',$approvalStats->total ?? 0],
   ['Pending',$approvalStats->pending_count ?? 0],
   ['Approved',$approvalStats->approved_count ?? 0],
   ['Rejected',$approvalStats->rejected_count ?? 0],
   ['Avg. turnaround hours',number_format((float)($approvalStats->avg_turnaround_hours ?? 0),1)],
  ] as $metric)
   <div class="col-6 col-lg"><div class="card h-100"><div class="card-body"><div class="small text-muted">{{ $metric[0] }}</div><div class="h3 mb-0">{{ $metric[1] }}</div></div></div></div>
  @endforeach
 </div>
 <div class="card mb-4"><div class="card-body"><h3 class="h6">Approval results by request type</h3>
  <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Request type</th><th>Total</th><th>Approved</th><th>Rejected</th><th>Pending</th></tr></thead><tbody>
  @forelse($approvalTypes as $row)<tr><td>{{ ucwords(str_replace('_',' ',$row->request_type ?: 'other')) }}</td><td>{{ $row->total }}</td><td>{{ $row->approved }}</td><td>{{ $row->rejected }}</td><td>{{ $row->pending }}</td></tr>@empty<tr><td colspan="5" class="text-muted">No approval requests in this period.</td></tr>@endforelse
  </tbody></table></div>
 </div></div>
 <div class="card mb-4"><div class="card-body">
  <h2 class="h5">Operational report exports</h2><p class="small text-muted">CSV exports use the selected date range and your business access scope.</p>
  <div class="d-flex flex-wrap gap-2">
   @foreach(['alerts'=>'Work queue alerts','approvals'=>'Approval requests','audit'=>'Activity audit trail'] as $key=>$label)
    <a class="btn btn-sm btn-outline-primary" href="{{ route('operational-insights.export',['dataset'=>$key,'from'=>$from,'to'=>$to]) }}">Export {{ $label }}</a>
   @endforeach
  </div>
  <div class="small text-muted mt-2">Activity records in this period: {{ $logCount }}</div>
 </div></div>
 <div class="card"><div class="card-body"><h2 class="h5">Recent activity</h2>
  <div class="table-responsive"><table class="table table-sm"><thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Subject</th></tr></thead><tbody>
  @forelse($recentLogs as $log)<tr><td>{{ $log->created_at->format('d M Y H:i') }}</td><td>{{ $log->actor->name ?? 'System' }}</td><td>{{ str_replace('.',' / ',$log->action) }}</td><td>{{ class_basename($log->subject_type ?: '—') }} #{{ $log->subject_id ?: '—' }}</td></tr>@empty<tr><td colspan="4" class="text-muted">No audit activity in this period.</td></tr>@endforelse
  </tbody></table></div>
 </div></div>
</div>
@endsection
