@extends('layouts.app')
@section('content')
<div class="container-fluid py-4">
 <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4"><div><h1 class="h3 mb-1">Automation Task Centre</h1><p class="text-muted mb-0">Internal task reminders, recurring schedules and completion performance.</p></div></div>
 @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
 @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
 <div class="row g-3 mb-4">
  <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Open tasks</div><div class="h3 mb-0">{{ number_format($stats['open']) }}</div></div></div></div>
  <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Overdue tasks</div><div class="h3 mb-0 text-danger">{{ number_format($stats['overdue']) }}</div></div></div></div>
  <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Completed in last 30 days</div><div class="h3 mb-0">{{ number_format($stats['completed_30d']) }}</div></div></div></div>
  <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">30-day completion rate*</div><div class="h3 mb-0">{{ $stats['completion_rate'] === null ? '—' : $stats['completion_rate'].'%' }}</div><div class="small text-muted">*Completed in period / created in period</div></div></div></div>
 </div>
 <div class="row g-3 mb-4">
  <div class="col-xl-6"><div class="card h-100"><div class="card-body"><h2 class="h5">Create one-time task</h2>
   <form method="POST" action="{{ route('automation.tasks.store') }}">@csrf
    <div class="mb-2"><label class="form-label">Task title</label><input name="title" class="form-control" maxlength="180" required></div>
    <div class="mb-2"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
    <div class="row g-2"><div class="col-md-5"><label class="form-label">Assign to</label><select name="assigned_to" class="form-select"><option value="">Unassigned</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select></div>
     <div class="col-md-3"><label class="form-label">Priority</label><select name="priority" class="form-select">@foreach(['low','normal','high','urgent'] as $p)<option value="{{ $p }}">{{ ucfirst($p) }}</option>@endforeach</select></div>
     <div class="col-md-4"><label class="form-label">Due at</label><input type="datetime-local" name="due_at" class="form-control"></div></div>
    <button class="btn btn-primary mt-3">Create task</button>
   </form>
  </div></div></div>
  <div class="col-xl-6"><div class="card h-100"><div class="card-body"><h2 class="h5">Create recurring task</h2><p class="small text-muted">A new task occurrence is generated when the schedule command runs. Existing tasks are never overwritten.</p>
   <form method="POST" action="{{ route('automation.recurring.store') }}">@csrf
    <div class="mb-2"><label class="form-label">Task title</label><input name="title" class="form-control" maxlength="180" required></div>
    <div class="mb-2"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
    <div class="row g-2"><div class="col-md-4"><label class="form-label">Frequency</label><select name="frequency" class="form-select"><option value="daily">Daily</option><option value="weekly">Weekly</option><option value="monthly">Monthly</option></select></div>
     <div class="col-md-3"><label class="form-label">Every</label><input type="number" name="interval_value" class="form-control" min="1" max="12" value="1" required></div>
     <div class="col-md-5"><label class="form-label">First due date</label><input type="datetime-local" name="next_due_at" class="form-control" required></div>
     <div class="col-md-5"><label class="form-label">Assign to</label><select name="assigned_to" class="form-select"><option value="">Unassigned</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select></div>
     <div class="col-md-3"><label class="form-label">Priority</label><select name="priority" class="form-select">@foreach(['low','normal','high','urgent'] as $p)<option value="{{ $p }}">{{ ucfirst($p) }}</option>@endforeach</select></div></div>
    <button class="btn btn-outline-primary mt-3">Save recurring schedule</button>
   </form>
  </div></div></div>
 </div>
 <div class="card mb-4"><div class="card-body"><div class="d-flex flex-wrap justify-content-between gap-2"><h2 class="h5">Task register</h2><form method="GET" class="d-flex gap-2"><select name="status" class="form-select form-select-sm"><option value="">All statuses</option>@foreach(['OPEN','COMPLETED','CANCELLED'] as $s)<option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst(strtolower($s)) }}</option>@endforeach</select><select name="priority" class="form-select form-select-sm"><option value="">All priorities</option>@foreach(['low','normal','high','urgent'] as $p)<option value="{{ $p }}" @selected(request('priority')===$p)>{{ ucfirst($p) }}</option>@endforeach</select><button class="btn btn-sm btn-outline-secondary">Filter</button></form></div>
  <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Task</th><th>Owner</th><th>Priority</th><th>Due</th><th>Status</th><th></th></tr></thead><tbody>
   @forelse($tasks as $task)<tr><td><strong>{{ $task->title }}</strong>@if($task->description)<div class="small text-muted">{{ \Illuminate\Support\Str::limit($task->description,100) }}</div>@endif @if($task->recurring_template_id)<span class="badge bg-info text-dark">Recurring</span>@endif</td><td>{{ $task->assignee->name ?? 'Unassigned' }}</td><td>{{ ucfirst($task->priority) }}</td><td>{{ $task->due_at?->format('d M Y H:i') ?? '—' }} @if($task->status==='OPEN' && $task->due_at?->isPast())<span class="badge bg-danger">Overdue</span>@endif</td><td>{{ ucfirst(strtolower($task->status)) }}</td><td>@if($task->status==='OPEN')<form method="POST" action="{{ route('automation.tasks.complete',$task) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-success">Complete</button></form>@endif</td></tr>
   @empty<tr><td colspan="6" class="text-center text-muted py-4">No tasks found.</td></tr>@endforelse
  </tbody></table></div>{{ $tasks->links() }}
 </div></div>
 <div class="row g-3"><div class="col-xl-6"><div class="card h-100"><div class="card-body"><h2 class="h5">Recurring schedules</h2>@forelse($templates as $template)<div class="d-flex justify-content-between align-items-center border-bottom py-2"><div><strong>{{ $template->title }}</strong><div class="small text-muted">Every {{ $template->interval_value }} {{ $template->frequency }} · Next: {{ $template->next_due_at->format('d M Y H:i') }} · {{ $template->is_active?'Active':'Paused' }}</div></div><form method="POST" action="{{ route('automation.recurring.toggle',$template) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-secondary">{{ $template->is_active?'Pause':'Resume' }}</button></form></div>@empty<p class="text-muted mb-0">No recurring schedules configured.</p>@endforelse</div></div></div>
  <div class="col-xl-6"><div class="card h-100"><div class="card-body"><h2 class="h5">Recent in-app reminders</h2>@forelse($reminders as $reminder)<div class="border-bottom py-2"><strong>{{ $reminder->task->title ?? 'Task removed' }}</strong><div class="small text-muted">{{ $reminder->user->name ?? 'Unassigned' }} · Reminder created {{ $reminder->reminded_at->format('d M Y H:i') }}</div></div>@empty<p class="text-muted mb-0">No reminders generated yet. The scheduled reminder command creates these records.</p>@endforelse</div></div></div></div>
</div>
@endsection
