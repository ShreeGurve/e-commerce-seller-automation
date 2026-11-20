@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-1">Marketplace Write Evidence Vault</h3>
            <p class="text-muted mb-0">Keep approval, submission and incident evidence organized by business.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="card mb-4">
        <div class="card-header fw-semibold">Add evidence record</div>
        <div class="card-body">
            <form method="POST" action="{{ route('marketplaces.writes.evidence.store') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Related to</label>
                        <select name="subject_type" class="form-select" required>
                            @foreach(['GENERAL','ATTEMPT','INCIDENT','RECOVERY'] as $v)
                                <option value="{{ $v }}" @selected(old('subject_type','GENERAL') === $v)>{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Related record ID</label>
                        <input type="number" min="1" name="subject_id" class="form-control" value="{{ old('subject_id') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Evidence type</label>
                        <select name="evidence_type" class="form-select" required>
                            @foreach(['PAYLOAD','RESPONSE','SCREENSHOT','APPROVAL','INVOICE','OTHER'] as $v)
                                <option value="{{ $v }}" @selected(old('evidence_type') === $v)>{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Reference code</label>
                        <input name="reference_code" maxlength="120" class="form-control" value="{{ old('reference_code') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Title</label>
                        <input name="title" maxlength="180" required class="form-control" value="{{ old('title') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">HTTPS evidence URL</label>
                        <input type="url" name="evidence_url" class="form-control" placeholder="https://..." value="{{ old('evidence_url') }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description / notes</label>
                        <textarea name="description" rows="2" maxlength="5000" class="form-control">{{ old('description') }}</textarea>
                    </div>
                    <div class="col-12"><button class="btn btn-primary">Save evidence</button></div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header fw-semibold">Evidence records</div>
        <div class="card-body">
            <form method="GET" class="row g-2 mb-3">
                <div class="col-md-3"><input name="search" class="form-control" placeholder="Title or reference" value="{{ $filters['search'] ?? '' }}"></div>
                <div class="col-md-3">
                    <select name="type" class="form-select">
                        <option value="">All evidence types</option>
                        @foreach(['PAYLOAD','RESPONSE','SCREENSHOT','APPROVAL','INVOICE','OTHER'] as $v)
                            <option value="{{ $v }}" @selected(($filters['type'] ?? '') === $v)>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="subject_type" class="form-select">
                        <option value="">All related records</option>
                        @foreach(['GENERAL','ATTEMPT','INCIDENT','RECOVERY'] as $v)
                            <option value="{{ $v }}" @selected(($filters['subject_type'] ?? '') === $v)>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3"><button class="btn btn-outline-primary">Filter</button> <a href="{{ route('marketplaces.writes.evidence.index') }}" class="btn btn-outline-secondary">Reset</a></div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead><tr><th>Title</th><th>Type</th><th>Related record</th><th>Reference</th><th>Created</th><th>Evidence</th></tr></thead>
                    <tbody>
                    @forelse($evidence as $item)
                        <tr>
                            <td class="fw-semibold">{{ $item->title }}</td>
                            <td><span class="badge bg-light text-dark">{{ $item->evidence_type }}</span></td>
                            <td>{{ $item->subject_type }}{{ $item->subject_id ? ' #'.$item->subject_id : '' }}</td>
                            <td>{{ $item->reference_code ?: '—' }}</td>
                            <td>{{ \Illuminate\Support\Carbon::parse($item->created_at)->format('d M Y H:i') }}</td>
                            <td>
                                @if($item->evidence_url)
                                    <a href="{{ $item->evidence_url }}" target="_blank" rel="noopener noreferrer">Open link</a>
                                @else
                                    <span class="text-muted">No link</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No evidence records found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            {{ $evidence->links() }}
        </div>
    </div>
</div>
@endsection
