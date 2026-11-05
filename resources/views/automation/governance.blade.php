@extends('layouts.app')
@section('content')
<div class="container-fluid py-4">
 <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div><h1 class="h3 mb-1">Automation Control Center</h1><p class="text-muted mb-0">Alert ownership, approval deadlines and a traceable decision history.</p></div>
 </div>
 @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
 @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
 <div class="row g-3 mb-4">
  <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="text-muted small">Open alert work items</div><div class="h3">{{ $events->total() }}</div><div class="small text-muted">Filtered to open alerts in your business</div></div></div></div>
  <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="text-muted small">Overdue approvals</div><div class="h3">{{ $approvals->count() }}</div><div class="small text-muted">Based on configured SLA hours</div></div></div></div>
  <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="text-muted small">Recent audit entries</div><div class="h3">{{ $logs->count() }}</div><div class="small text-muted">Latest recorded workflow actions</div></div></div></div>
 </div>
 <div class="card mb-4"><div class="card-body">
  <h2 class="h5">Alert work queue</h2>
  @if($events->isEmpty())<p class="text-muted mb-0">No open alert work items.</p>
  @else
  <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Alert</th><th>Priority</th><th>Owner / deadline</th><th>Update work item</th><th></th></tr></thead><tbody>
  @foreach($events as $event)
   <tr>
    <td><strong>{{ $event->title }}</strong><div class="small text-muted">{{ $event->message }}</div></td>
    <td><span class="badge bg-{{ $event->priority==='urgent'?'danger':($event->priority==='high'?'warning':'secondary') }}">{{ ucfirst($event->priority ?: 'normal') }}</span></td>
    <td>{{ $event->assignee->name ?? 'Unassigned' }}<div class="small text-muted">{{ $event->due_at?->format('d M Y H:i') ?? 'No due date' }}</div></td>
    <td><form method="POST" action="{{ route('governance.alerts.assign',$event) }}" class="d-flex flex-wrap gap-1">@csrf @method('PATCH')
     <select name="assigned_to" class="form-select form-select-sm" style="max-width:150px"><option value="">Unassigned</option>@foreach($users as $u)<option value="{{ $u->id }}" @selected((int)$event->assigned_to===(int)$u->id)>{{ $u->name }}</option>@endforeach</select>
     <input type="datetime-local" name="due_at" class="form-control form-control-sm" style="max-width:190px" value="{{ $event->due_at?->format('Y-m-d\TH:i') }}">
     <select name="priority" class="form-select form-select-sm" style="max-width:110px">@foreach(['low','normal','high','urgent'] as $p)<option value="{{ $p }}" @selected(($event->priority ?: 'normal')===$p)>{{ ucfirst($p) }}</option>@endforeach</select>
     <button class="btn btn-sm btn-outline-primary">Save</button>
    </form></td>
    <td><form method="POST" action="{{ route('governance.alerts.resolve',$event) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-success">Resolve</button></form></td>
   </tr>
  @endforeach
  </tbody></table></div>{{ $events->links() }}
  @endif
 </div></div>
 <div class="card mb-4"><div class="card-body">
  <h2 class="h5">Approval SLA policies</h2><p class="small text-muted">SLA rules flag pending requests for review; they do not approve, reject or escalate a request automatically.</p>
  @foreach(['expense','purchase','price_change','refund','inventory_adjustment','other'] as $type)
   @php($policy=$policies->get($type))
   <form method="POST" action="{{ route('governance.approval-sla.save') }}" class="row g-2 align-items-end border-top py-2">@csrf
    <input type="hidden" name="request_type" value="{{ $type }}">
    <div class="col-md-4"><strong>{{ ucwords(str_replace('_',' ',$type)) }}</strong></div>
    <div class="col-md-3"><label class="form-label small">SLA hours</label><input type="number" name="sla_hours" min="1" max="720" class="form-control form-control-sm" value="{{ $policy->sla_hours ?? 48 }}" required></div>
    <div class="col-md-2"><label class="form-label small">Enabled</label><select name="is_enabled" class="form-select form-select-sm"><option value="1" @selected(($policy->is_enabled ?? true)==true)>Yes</option><option value="0" @selected(($policy->is_enabled ?? true)==false)>No</option></select></div>
    <div class="col-md-3"><button class="btn btn-sm btn-outline-primary">Save policy</button></div>
   </form>
  @endforeach
 </div></div>
 <div class="card mb-4"><div class="card-body"><h2 class="h5">Overdue approval requests</h2>
  @if($approvals->isEmpty())<p class="text-muted mb-0">No overdue pending approvals.</p>@else
  <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Request</th><th>Type</th><th>Requester</th><th>Submitted</th><th>Age</th></tr></thead><tbody>
  @foreach($approvals as $approval)<tr><td>{{ $approval->title }} <span class="text-muted">({{ $approval->reference }})</span></td><td>{{ $approval->request_type ?: $approval->type }}</td><td>{{ $approval->requester->name ?? '—' }}</td><td>{{ $approval->created_at->format('d M Y H:i') }}</td><td>{{ $approval->created_at->diffForHumans() }}</td></tr>@endforeach
  </tbody></table></div>@endif
 </div></div>
 <div class="card"><div class="card-body"><h2 class="h5">Recent activity & decision audit</h2>
  @if($logs->isEmpty())<p class="text-muted mb-0">No new audit entries yet. New alert changes and approval decisions will appear here.</p>@else
  <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>When</th><th>Who</th><th>Action</th><th>Details</th></tr></thead><tbody>
  @foreach($logs as $log)<tr><td>{{ $log->created_at->format('d M Y H:i') }}</td><td>{{ $log->actor->name ?? 'System' }}</td><td>{{ str_replace('.',' / ',$log->action) }}</td><td class="small">{{ \Illuminate\Support\Str::limit(json_encode($log->details),180) }}</td></tr>@endforeach
  </tbody></table></div>@endif
 </div></div>
</div>
@endsection
