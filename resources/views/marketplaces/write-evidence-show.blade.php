@extends('layouts.app')
@section('content')
<div class="container py-4">
    <a href="{{ route('marketplaces.writes.evidence.index') }}" class="btn btn-link px-0">← Back to evidence vault</a>
    <div class="card">
        <div class="card-header fw-semibold">Evidence record #{{ $item->id }}</div>
        <div class="card-body">
            <h4>{{ $item->title }}</h4>
            <p>{{ $item->description ?: 'No description provided.' }}</p>
            <dl class="row">
                <dt class="col-sm-3">Evidence type</dt><dd class="col-sm-9">{{ $item->evidence_type }}</dd>
                <dt class="col-sm-3">Related record</dt><dd class="col-sm-9">{{ $item->subject_type }} {{ $item->subject_id ? '#'.$item->subject_id : '' }}</dd>
                <dt class="col-sm-3">Reference</dt><dd class="col-sm-9">{{ $item->reference_code ?: '—' }}</dd>
                <dt class="col-sm-3">Evidence URL</dt><dd class="col-sm-9">@if($item->evidence_url)<a href="{{ $item->evidence_url }}" target="_blank" rel="noopener noreferrer">{{ $item->evidence_url }}</a>@else — @endif</dd>
                <dt class="col-sm-3">Created</dt><dd class="col-sm-9">{{ $item->created_at }}</dd>
            </dl>
        </div>
    </div>
</div>
@endsection
