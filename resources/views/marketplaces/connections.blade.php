@extends('layouts.app')
@section('title', 'Marketplace Connections')
@section('subtitle', 'Amazon and Flipkart API readiness · credentials are never displayed here')
@section('content')
    @php
        $channels = [
            'amazon' => ['label' => 'Amazon SP-API', 'data' => $status['amazon']],
            'flipkart' => ['label' => 'Flipkart Seller API', 'data' => $status['flipkart']],
        ];
        $statusLabel = fn($s) => match($s) {
            'READY_FOR_AUTH_CHECK' => 'Credentials configured · authorization check required',
            'PENDING_CREDENTIALS' => 'Pending credentials',
            'DISABLED' => 'Configured but disabled',
            default => 'Not configured',
        };
    @endphp
    <div class="alert alert-info">
        <strong>Safe integration mode:</strong> Live product publishing, order actions and inventory writes remain blocked until each official API connection is authorized and its operation-specific adapter is verified.
    </div>
    <div class="row g-3 mb-3">
        @foreach($channels as $key => $channel)
            @php $d = $channel['data']; @endphp
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <strong>{{ $channel['label'] }}</strong>
                        <span class="badge text-bg-{{ $d['configured'] ? 'warning' : 'secondary' }}">{{ $statusLabel($d['status']) }}</span>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">{{ $d['message'] }}</p>
                        <div class="small fw-semibold mb-2">Configuration checklist</div>
                        @if(count($d['missing']))
                            <ul class="small text-danger mb-3">
                                @foreach($d['missing'] as $missing)<li>{{ strtoupper(str_replace('_', ' ', $missing)) }}</li>@endforeach
                            </ul>
                        @else
                            <div class="small text-success mb-3">Required environment values are present. Live authorization has not yet been verified.</div>
                        @endif
                        <div class="small text-muted">API calls enabled: {{ $d['api_calls_enabled'] ? 'Yes' : 'No' }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    <div class="card mb-3">
        <div class="card-header">Global write safety</div>
        <div class="card-body">
            <div class="d-flex justify-content-between"><span>Marketplace push switch</span><strong>{{ $status['push_enabled'] ? 'Enabled' : 'Disabled' }}</strong></div>
            <div class="d-flex justify-content-between"><span>Automatic inventory sync</span><strong>{{ $status['inventory_auto_sync'] ? 'Enabled' : 'Disabled' }}</strong></div>
            <div class="d-flex justify-content-between"><span>Price push approval</span><strong>Required</strong></div>
            <p class="small text-muted mt-2 mb-0">Enabling these switches alone does not activate writes. The actual marketplace API operation must be implemented, authorized and tested first.</p>
        </div>
    </div>
    <div class="card">
        <div class="card-header">Seller account setup</div>
        <div class="card-body">
            <h6>Amazon</h6>
            <ol class="small">
                <li>Register a Selling Partner API application in Amazon Developer Central.</li>
                <li>Authorize the application from the seller account and obtain the LWA refresh token.</li>
                <li>Confirm the India marketplace ID, SP-API regional endpoint and required roles.</li>
                <li>Configure credentials in the server-side <code>.env</code> file.</li>
            </ol>
            <h6 class="mt-3">Flipkart</h6>
            <ol class="small">
                <li>Open Seller Dashboard → Manage Profile → Developer Access.</li>
                <li>Create/enable Self Access API credentials for the seller account.</li>
                <li>Confirm the API base URL and supported permissions with Flipkart.</li>
                <li>Configure credentials in the server-side <code>.env</code> file.</li>
            </ol>
            <div class="alert alert-warning small mb-0">Never paste API secrets into support chats, source control or this page. Keep <code>.env</code> outside shared ZIP files and rotate any credentials previously exposed.</div>
        </div>
    </div>
@endsection
