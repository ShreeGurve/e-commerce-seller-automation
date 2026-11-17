@extends('layouts.app')
@section('title', 'Advanced Workflow Analytics')
@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3"><div><h1 class="h3 mb-1">Advanced Workflow Analytics</h1><p class="text-muted mb-0">Task activity and completion trends for the selected period.</p></div><form method="GET"><select name="days" class="form-select" onchange="this.form.submit()">@foreach([7,14,30,60,90] as $period)<option value="{{ $period }}" @selected($days===$period)>{{ $period }} days</option>@endforeach</select></form></div>
    <div class="row g-3 mb-4">@foreach(['created'=>'Created','completed'=>'Completed','open'=>'Open now','overdue'=>'Overdue','completion_rate'=>'Completion ratio','avg_hours'=>'Average completion hours','on_time'=>'Completed on time'] as $key=>$label)<div class="col-6 col-md-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">{{ $label }}</div><div class="h3 mb-0">{{ in_array($key,['completion_rate']) ? (is_null($stats[$key])?'—':$stats[$key].'%') : (in_array($key,['avg_hours']) ? (is_null($stats[$key])?'—':number_format((float)$stats[$key],1)) : number_format((float)$stats[$key])) }}</div></div></div></div>@endforeach</div>
    <div class="card"><div class="card-header fw-semibold">Daily workflow activity</div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Date</th><th>Tasks created</th><th>Tasks completed</th></tr></thead><tbody>
    @forelse($daily as $item)<tr><td>{{ \Illuminate\Support\Carbon::parse($item->day)->format('d M Y') }}</td><td>{{ $item->created_count }}</td><td>{{ $completionDaily[$item->day]->completed_count ?? 0 }}</td></tr>@empty<tr><td colspan="3" class="text-center text-muted py-4">No workflow activity in this period.</td></tr>@endforelse
    </tbody></table></div></div>
</div>
@endsection
