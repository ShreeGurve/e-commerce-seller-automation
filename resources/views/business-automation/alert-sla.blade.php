@extends('layouts.app')
@section('title', 'Alert Follow-up SLA')
@section('content')
<div class="container-fluid py-3">
 <div class="mb-3"><h1 class="h3 mb-1">Alert Follow-up SLA</h1><p class="text-muted mb-0">Monitors existing alert due dates and assignment. Manage assignments in the Automation Control Center.</p></div>
 <div class="row g-3 mb-3"><div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted">Overdue</div><div class="h3 mb-0">{{ $counts['overdue'] }}</div></div></div></div><div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted">Due today</div><div class="h3 mb-0">{{ $counts['today'] }}</div></div></div></div><div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted">Unassigned open alerts</div><div class="h3 mb-0">{{ $counts['unassigned'] }}</div></div></div></div></div>
 <form method="get" class="mb-3"><select name="filter" class="form-select" style="max-width:220px" onchange="this.form.submit()"><option value="overdue" @selected($filter==='overdue')>Overdue</option><option value="due-today" @selected($filter==='due-today')>Due today</option><option value="upcoming" @selected($filter==='upcoming')>Upcoming</option><option value="all" @selected($filter==='all')>All with due date</option></select></form>
 <div class="card"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Alert</th><th>Priority</th><th>Assignee</th><th>Due</th><th>Age</th></tr></thead><tbody>
 @forelse($events as $event)<tr><td><div class="fw-semibold">{{ $event->title }}</div><small class="text-muted">{{ $event->message }}</small></td><td>{{ strtoupper($event->priority ?? 'normal') }}</td><td>{{ $event->assignee->name ?? 'Unassigned' }}</td><td>{{ optional($event->due_at)->format('d M Y H:i') }}</td><td>{{ $event->created_at ? $event->created_at->diffForHumans() : '—' }}</td></tr>@empty<tr><td colspan="5" class="text-center text-muted py-4">No matching open alerts with a due date.</td></tr>@endforelse
 </tbody></table></div></div>
</div>
@endsection
