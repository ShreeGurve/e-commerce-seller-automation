@extends('layouts.app')
@section('title', 'Marketplace Platforms')
@section('subtitle', 'Add seller platforms one at a time · verify credentials before saving')
@section('content')
    <div class="alert alert-info">
        <strong>Secure connection workflow:</strong> Credentials are tested with the platform before they are saved. Failed credentials are not stored. Saved credentials are encrypted in the database and never displayed again.
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-1">Connected platforms</h5>
            <div class="small text-muted">Each business can connect its seller account independently.</div>
        </div>
        <span class="badge text-bg-primary">{{ $connections->count() }} connected</span>
    </div>

    @forelse($connections as $connection)
        <div class="card mb-3">
            <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <strong>{{ $connection->marketplace->name }}</strong>
                        <span class="badge text-bg-{{ $connection->environment === 'sandbox' ? 'info' : 'success' }}">{{ ucfirst($connection->environment ?? 'production') }}</span>
                        <span class="badge text-bg-success">Verified</span>
                    </div>
                    <div class="small text-muted">{{ $connection->account_label }} · Verified {{ optional($connection->verified_at)->format('d M Y, h:i A') }}</div>
                    <div class="small text-muted mt-1">{{ $connection->last_verified_message }}</div>
                </div>
                <div class="d-flex gap-2">
                    <a class="btn btn-outline-primary btn-sm" href="{{ route('marketplaces.connections.create', ['marketplace' => $connection->marketplace, 'environment' => $connection->environment ?? 'production']) }}">Reconnect / Update</a>
                    <form method="POST" action="{{ route('marketplaces.connections.destroy', $connection) }}" onsubmit="return confirm('Remove this marketplace connection?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-outline-danger btn-sm">Remove</button>
                    </form>
                </div>
            </div>
        </div>
    @empty
        <div class="card mb-3"><div class="card-body text-center text-muted py-4">No marketplace platforms connected yet. Add your first platform below.</div></div>
    @endforelse

    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <div>
            <h5 class="mb-1">Add a platform</h5>
            <div class="small text-muted">Choose one platform, enter its API credentials and verify.</div>
        </div>
    </div>
    <div class="row g-3">
        @forelse($availablePlatforms as $platform)
            @php $supported = in_array($platform->code, ['amazon', 'flipkart'], true); @endphp
            <div class="col-md-6 col-xl-4">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0">{{ $platform->name }}</h6>
                            <span class="badge text-bg-{{ $supported ? 'success' : 'secondary' }}">{{ $supported ? 'Verification available' : 'Adapter pending' }}</span>
                        </div>
                        <p class="small text-muted flex-grow-1">{{ $supported ? 'Connect your seller account. Credentials will only be saved after the platform accepts the authentication request.' : 'This platform is registered in the platform directory, but its authentication adapter must be implemented before credentials can be verified.' }}</p>
                        @if($supported && $platform->code === 'amazon')
                            @php $hasSandbox = $connections->contains(fn($c) => $c->marketplace_id === $platform->id && ($c->environment ?? 'production') === 'sandbox'); $hasProduction = $connections->contains(fn($c) => $c->marketplace_id === $platform->id && ($c->environment ?? 'production') === 'production'); @endphp
                            <div class="d-flex flex-wrap gap-2">
                                @unless($hasSandbox)<a class="btn btn-info btn-sm" href="{{ route('marketplaces.connections.create', ['marketplace' => $platform, 'environment' => 'sandbox']) }}">Add Amazon Sandbox</a>@endunless
                                @unless($hasProduction)<a class="btn btn-primary btn-sm" href="{{ route('marketplaces.connections.create', ['marketplace' => $platform, 'environment' => 'production']) }}">Add Amazon Production</a>@endunless
                                @if($hasSandbox && $hasProduction)<span class="small text-success">Both environments configured</span>@endif
                            </div>
                        @elseif($supported)
                            @unless($connections->contains(fn($c) => $c->marketplace_id === $platform->id && ($c->environment ?? 'production') === 'production'))
                                <a class="btn btn-primary btn-sm" href="{{ route('marketplaces.connections.create', ['marketplace' => $platform, 'environment' => 'production']) }}">Add {{ $platform->name }}</a>
                            @else<span class="small text-success">Connected</span>@endunless
                        @else
                            <button class="btn btn-outline-secondary btn-sm" disabled>Not available yet</button>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12"><div class="alert alert-warning">No platform definitions are available. Add marketplace definitions to the platform directory first.</div></div>
        @endforelse
    </div>

    <div class="alert alert-warning small mt-4 mb-0">
        <strong>Important:</strong> Successful credential verification confirms authentication only. Amazon SP-API role permissions and Flipkart operation-level access still require separate read-only API tests before product, order or inventory synchronization is enabled.
    </div>
@endsection
