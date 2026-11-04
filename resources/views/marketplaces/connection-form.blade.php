@extends('layouts.app')
@section('title', 'Connect '.$marketplace->name)
@section('subtitle', 'Verify credentials first · only successful credentials are saved')
@section('content')
    <div class="row justify-content-center">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>{{ $marketplace->name }} {{ $environment === 'sandbox' ? 'Sandbox' : 'Production' }} connection</strong>
                    <a href="{{ route('marketplaces.connections') }}" class="btn btn-sm btn-outline-secondary">Back to platforms</a>
                </div>
                <div class="card-body">
                    @if($errors->any())
                        <div class="alert alert-danger"><strong>Verification failed.</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul><div class="small mt-2">Nothing from this failed attempt has been saved. Correct the details and try again.</div></div>
                    @endif
                    <form method="POST" action="{{ route('marketplaces.connections.verify', $marketplace) }}" autocomplete="off">
                        @csrf
                        <input type="hidden" name="environment" value="{{ $environment }}">
                        @if($marketplace->code === 'amazon' && $environment === 'sandbox')
                            <div class="alert alert-info small"><strong>Sandbox mode:</strong> Uses Amazon's mock API environment. It will not access or change your real seller listings, orders or inventory. Use the credentials from <em>View sandbox credentials</em> and the Sandbox Testing page.</div>
                        @endif
                        <div class="mb-3">
                            <label class="form-label">Connection name / seller account label</label>
                            <input class="form-control" name="account_label" value="{{ old('account_label', $marketplace->name.' Seller Account') }}" required maxlength="100">
                        </div>

                        @if($marketplace->code === 'amazon')
                            <div class="alert alert-secondary small">{{ $environment === 'sandbox' ? 'Use the Sandbox Client ID, Sandbox Client Secret and Sandbox Refresh Token from Developer Central.' : 'Use your authorized Production SP-API application LWA credentials.' }}</div>
                            <div class="mb-3"><label class="form-label">LWA Client ID</label><input class="form-control" name="client_id" value="{{ old('client_id') }}" required autocomplete="off"></div>
                            <div class="mb-3"><label class="form-label">LWA Client Secret</label><input class="form-control" type="password" name="client_secret" required autocomplete="new-password"></div>
                            <div class="mb-3"><label class="form-label">LWA Refresh Token</label><textarea class="form-control" name="refresh_token" rows="3" required autocomplete="off"></textarea></div>
                            <div class="row">
                                <div class="col-md-7 mb-3"><label class="form-label">Marketplace ID</label><input class="form-control" name="marketplace_id" value="{{ old('marketplace_id', 'A21TJRUUN4KGV') }}" required></div>
                                <div class="col-md-5 mb-3"><label class="form-label">SP-API region</label><select class="form-select" name="region" required><option value="eu" @selected(old('region', 'eu') === 'eu')>Europe (EU)</option><option value="na" @selected(old('region') === 'na')>North America (NA)</option><option value="fe" @selected(old('region') === 'fe')>Far East (FE)</option></select></div>
                            </div>
                            <div class="small text-muted mb-3">{{ $environment === 'sandbox' ? 'Verification exchanges the sandbox refresh token, then calls the official static Sandbox Orders test case. The returned data is simulated.' : 'Verification exchanges the refresh token with Amazon LWA. It does not yet test SP-API IAM signing or operation roles.' }}</div>
                        @elseif($marketplace->code === 'flipkart')
                            <div class="alert alert-secondary small">Create a Self Access application in Flipkart Seller Hub → Manage Profile → Developer Access.</div>
                            <div class="mb-3"><label class="form-label">Application ID</label><input class="form-control" name="app_id" value="{{ old('app_id') }}" required autocomplete="off"></div>
                            <div class="mb-3"><label class="form-label">Application Secret</label><input class="form-control" type="password" name="app_secret" required autocomplete="new-password"></div>
                            <div class="mb-3"><label class="form-label">Seller ID (optional, for account mapping)</label><input class="form-control" name="seller_id" value="{{ old('seller_id') }}"></div>
                            <div class="small text-muted mb-3">Verification requests a Seller API access token. Operation-level access will be tested separately.</div>
                        @endif

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Verify credentials &amp; save</button>
                            <a href="{{ route('marketplaces.connections') }}" class="btn btn-light">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
            <div class="alert alert-warning small mt-3">Never share API credentials in chat, screenshots or source control. Credentials are encrypted at rest after successful verification.</div>
        </div>
    </div>
@endsection
