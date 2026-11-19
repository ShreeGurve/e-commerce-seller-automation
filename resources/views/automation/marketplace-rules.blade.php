@extends('layouts.app')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div><h1 class="h3 mb-1">Marketplace Automation Rules</h1><p class="text-muted mb-0">Turn recommendations into reviewable team tasks — without automatic marketplace changes.</p></div>
        <form method="POST" action="{{ route('automation.marketplace-rules.generate') }}" class="d-flex gap-2">
            @csrf
            <select name="days" class="form-select" aria-label="Analysis period"><option value="7">7 days</option><option value="14">14 days</option><option value="30" selected>30 days</option><option value="60">60 days</option><option value="90">90 days</option></select>
            <button class="btn btn-primary">Run recommendation scan</button>
        </form>
    </div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white"><h2 class="h5 mb-0">Create automation rule</h2></div><div class="card-body">
        <form method="POST" action="{{ route('automation.marketplace-rules.store') }}" class="row g-3">@csrf
            <div class="col-md-3"><label class="form-label">Rule name</label><input name="name" class="form-control" maxlength="120" required></div>
            <div class="col-md-3"><label class="form-label">Recommendation type</label><select name="recommendation_type" class="form-select" required>@foreach($types as $type)<option value="{{ $type }}">{{ str_replace('_',' ',$type) }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label">Priority</label><select name="priority_override" class="form-select"><option value="">Use recommendation</option><option>HIGH</option><option>MEDIUM</option><option>LOW</option></select></div>
            <div class="col-md-2"><label class="form-label">Cooldown (hours)</label><input type="number" name="cooldown_hours" class="form-control" min="1" max="720" value="24" required></div>
            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-outline-primary w-100">Add rule</button></div>
            <div class="col-12"><label class="form-label">Suggested action guidance</label><input name="action_guidance" class="form-control" maxlength="500" placeholder="Optional instructions for the reviewer"></div>
        </form>
    </div></div>

    <div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white"><h2 class="h5 mb-0">Configured rules</h2></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Rule</th><th>Signal</th><th>Priority</th><th>Cooldown</th><th>Status</th></tr></thead><tbody>
        @forelse($rules as $rule)<tr><td><strong>{{ $rule->name }}</strong><div class="small text-muted">{{ $rule->action_guidance }}</div></td><td>{{ str_replace('_',' ',$rule->recommendation_type) }}</td><td>{{ $rule->priority_override ?: 'Original' }}</td><td>{{ $rule->cooldown_hours }}h</td><td><form method="POST" action="{{ route('automation.marketplace-rules.toggle',$rule->id) }}">@csrf @method('PATCH')<input type="hidden" name="is_enabled" value="{{ $rule->is_enabled ? 0 : 1 }}"><button class="btn btn-sm {{ $rule->is_enabled ? 'btn-success' : 'btn-outline-secondary' }}">{{ $rule->is_enabled ? 'Enabled' : 'Disabled' }}</button></form></td></tr>
        @empty<tr><td colspan="5" class="text-center text-muted py-4">No automation rules configured.</td></tr>@endforelse
    </tbody></table></div></div>

    <div class="card border-0 shadow-sm"><div class="card-header bg-white"><h2 class="h5 mb-0">Generated tasks</h2><small class="text-muted">Approval records only. Approved tasks do not execute live actions.</small></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Task</th><th>Priority</th><th>Status</th><th>Reason / Suggested action</th><th>Review</th></tr></thead><tbody>
        @forelse($tasks as $task)<tr><td><strong>{{ $task->title }}</strong><div class="small text-muted">SKU product ID: {{ $task->product_id ?? '—' }}</div></td><td><span class="badge text-bg-{{ $task->priority === 'HIGH' ? 'danger' : ($task->priority === 'MEDIUM' ? 'warning' : 'secondary') }}">{{ $task->priority }}</span></td><td>{{ $task->status }}</td><td><div>{{ $task->reason }}</div><div class="small text-muted">{{ $task->suggested_action }}</div></td><td>
            @if($task->status === 'PENDING')<form method="POST" action="{{ route('automation.marketplace-rules.review',$task->id) }}" class="d-flex flex-column gap-1">@csrf<select name="decision" class="form-select form-select-sm"><option value="APPROVED">Approve task</option><option value="REJECTED">Reject task</option></select><input name="review_note" class="form-control form-control-sm" placeholder="Review note"><button class="btn btn-sm btn-outline-primary">Save review</button></form>
            @elseif($task->status === 'APPROVED')<form method="POST" action="{{ route('automation.marketplace-rules.review',$task->id) }}">@csrf<input type="hidden" name="decision" value="COMPLETED"><input name="review_note" class="form-control form-control-sm mb-1" placeholder="Completion note"><button class="btn btn-sm btn-outline-success">Mark completed</button></form>@else<span class="text-muted small">{{ $task->review_note }}</span>@endif
        </td></tr>@empty<tr><td colspan="5" class="text-center text-muted py-4">No tasks generated yet. Run a recommendation scan to begin.</td></tr>@endforelse
    </tbody></table></div></div>
</div>
@endsection
