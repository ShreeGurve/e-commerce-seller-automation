<?php

namespace App\Services;

use App\Models\MarketplaceConnection;

/** Resolves a seller connection inside an explicitly supplied business boundary. */
class MarketplaceConnectionResolver
{
    public function resolve(int $businessId, string $marketplaceCode, string $environment = 'production'): MarketplaceConnection
    {
        return MarketplaceConnection::query()
            ->with('marketplace')
            ->where('business_id', $businessId)
            ->where('environment', $environment)
            ->whereHas('marketplace', fn ($query) => $query->where('code', $marketplaceCode))
            ->firstOrFail();
    }
}
