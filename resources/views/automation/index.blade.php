@extends('layouts.app')
@section('title', 'Business Action Centre')
@section('subtitle', 'One place to review operational risks and run internal business checks')
@section('content')
    <style>
        .ac-hero{border:1px solid var(--bs-border-color);border-radius:16px;padding:22px;background:linear-gradient(120deg,rgba(13,110,253,.08),rgba(13,110,253,.015))}
        .ac-stat{height:100%;border:1px solid var(--bs-border-color);border-radius:14px;padding:16px;background:var(--bs-body-bg)}
        .ac-stat .value{font-size:1.7rem;font-weight:700;line-height:1.2}
        .ac-muted{color:var(--bs-secondary-color)}
        .ac-severity{display:inline-flex;align-items:center;border-radius:20px;padding:4px 10px;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.03em}
        .ac-critical{background:#fce8e6;color:#a12622}.ac-high{background:#fff0db;color:#965500}.ac-medium{background:#e8f1ff;color:#1555a5}.ac-low{background:#e8f5ed;color:#17633a}
        @media(max-width:767px){.ac-hero{padding:16px}.ac-table{min-width:760px}}
    </style>
    <div class="ac-hero mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="small fw-semibold text-primary text-uppercase mb-1">Phase 12 · Action Centre</div>
                <h4 class="mb-1">Stay ahead of operational issues</h4>
                <div class="ac-muted">Review business alerts, configure thresholds and run checks when you need them.</div>
            </div>
            @can('automation.manage')
                <form method="POST" action="{{ route('automation.run') }}">@csrf
                    <button class="btn btn-primary px-4"><i class="bi bi-play-fill me-1"></i> Run checks now</button>
                </form>
            @endcan
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3"><div class="ac-stat"><div class="ac-muted small mb-2">All recorded alerts</div><div class="value">{{ number_format($alertCounts['total']) }}</div><div class="small ac-muted mt-1">Historical automation events</div></div></div>
        <div class="col-6 col-xl-3"><div class="ac-stat"><div class="ac-muted small mb-2">Critical</div><div class="value text-danger">{{ number_format($alertCounts['critical']) }}</div><div class="small ac-muted mt-1">Requires urgent review</div></div></div>
        <div class="col-6 col-xl-3"><div class="ac-stat"><div class="ac-muted small mb-2">High priority</div><div class="value" style="color:#b36b00">{{ number_format($alertCounts['high']) }}</div><div class="small ac-muted mt-1">Needs timely action</div></div></div>
        <div class="col-6 col-xl-3"><div class="ac-stat"><div class="ac-muted small mb-2">Medium priority</div><div class="value text-primary">{{ number_format($alertCounts['medium']) }}</div><div class="small ac-muted mt-1">Monitor and plan</div></div></div>
    </div>

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
        <div><h5 class="mb-0">Automation rules</h5><div class="small ac-muted">Thresholds are configurable; checks create internal alerts only.</div></div>
    </div>
    @can('automation.manage')
        <div class="card mb-4"><div class="card-body">
            <form method="POST" action="{{ route('automation.store') }}" class="row g-3 align-items-end">@csrf
                <div class="col-md-3"><label class="form-label">Rule name</label><input name="name" class="form-control" required maxlength="120" placeholder="e.g. Late dispatch watch"></div>
                <div class="col-md-3"><label class="form-label">Trigger</label><select name="trigger_key" class="form-select">@foreach($triggers as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
                <div class="col-md-2"><label class="form-label">Threshold</label><input name="threshold" type="number" step=".01" min="0" value="3" class="form-control" required><div class="form-text">Days / units / ACOS % / INR</div></div>
                <div class="col-md-2"><div class="form-check mb-2"><input type="checkbox" name="is_active" value="1" class="form-check-input" checked id="ruleActive"><label for="ruleActive" class="form-check-label">Enabled</label></div></div>
                <div class="col-md-2"><button class="btn btn-outline-primary w-100">Add rule</button></div>
            </form>
        </div></div>
    @endcan
    <div class="card mb-4"><div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th class="ps-3">Rule</th><th>Trigger</th><th>Threshold</th><th>Status</th><th>Last checked</th><th>Alerts</th><th class="text-end pe-3">Actions</th></tr></thead>
        <tbody>@forelse($rules as $rule)<tr>
            <td class="ps-3 fw-semibold">{{ $rule->name }}</td><td>{{ $triggers[$rule->trigger_key] ?? $rule->trigger_key }}</td><td>{{ $rule->threshold }}</td>
            <td><span class="badge {{ $rule->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $rule->is_active ? 'Enabled' : 'Paused' }}</span></td>
            <td>{{ $rule->last_run_at?->diffForHumans() ?? 'Never' }}</td><td>{{ number_format($rule->logs_count) }}</td>
            <td class="text-end pe-3"><div class="d-inline-flex gap-1">
                @can('automation.manage')<form method="POST" action="{{ route('automation.toggle',$rule) }}">@csrf<button class="btn btn-sm btn-outline-primary">{{ $rule->is_active ? 'Pause' : 'Enable' }}</button></form>
                <form method="POST" action="{{ route('automation.destroy',$rule) }}" onsubmit="return confirm('Delete this rule?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form>@endcan
            </div></td>
        </tr>@empty<tr><td colspan="7" class="text-center text-muted py-4">No rules configured yet. Add a rule to start monitoring your operations.</td></tr>@endforelse</tbody>
    </table></div></div>

    <div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-3">
        <div><h5 class="mb-1">Alert history</h5><div class="small ac-muted">Latest 100 matching events. Historical alerts remain available for audit.</div></div>
        <form method="GET" action="{{ route('automation.index') }}" class="d-flex flex-wrap gap-2">
            <input name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Search alerts" style="min-width:190px">
            <select name="severity" class="form-select form-select-sm" style="min-width:145px"><option value="">All priorities</option>@foreach(['critical'=>'Critical','high'=>'High','medium'=>'Medium','low'=>'Low'] as $key=>$label)<option value="{{ $key }}" @selected(($filters['severity'] ?? '')===$key)>{{ $label }}</option>@endforeach</select>
            <button class="btn btn-sm btn-outline-secondary">Filter</button><a class="btn btn-sm btn-light" href="{{ route('automation.index') }}">Reset</a>
        </form>
    </div>
    <div class="card"><div class="table-responsive"><table class="table align-middle mb-0 ac-table">
        <thead><tr><th class="ps-3">Detected</th><th>Priority</th><th>Alert</th><th>Details</th><th>Rule</th><th class="text-end pe-3">Next step</th></tr></thead>
        <tbody>@forelse($logs as $log)<tr>
            <td class="ps-3 text-nowrap"><div>{{ $log->created_at->format('d M Y') }}</div><div class="small ac-muted">{{ $log->created_at->format('H:i') }}</div></td>
            <td><span class="ac-severity ac-{{ in_array($log->severity,['critical','high','medium','low']) ? $log->severity : 'medium' }}">{{ $log->severity }}</span></td>
            <td class="fw-semibold">{{ $log->title }}</td><td class="text-wrap" style="min-width:240px">{{ $log->message }}</td><td>{{ $log->rule->name ?? 'Deleted rule' }}</td>
            <td class="text-end pe-3">@if($log->target_url)<a href="{{ $log->target_url }}" class="btn btn-sm btn-outline-primary">Review <i class="bi bi-arrow-up-right ms-1"></i></a>@else<span class="text-muted small">Review manually</span>@endif</td>
        </tr>@empty<tr><td colspan="6" class="text-center text-muted py-5"><i class="bi bi-check2-circle fs-3 d-block mb-2"></i>No alerts match these filters.</td></tr>@endforelse</tbody>
    </table></div></div>
    <div class="small ac-muted mt-3">Automation is internal-only. It does not publish prices, change listings, place supplier orders or contact marketplaces. TEST orders are excluded from sales and dispatch alerts.</div>
@endsection
