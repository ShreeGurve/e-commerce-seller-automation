<?php

namespace App\Marketplaces\Sync;

use RuntimeException;

class MarketplaceReadProviderRegistry
{
    public function for(string $marketplaceCode): MarketplaceReadProvider
    {
        $providers = config('marketplace_sync.providers', []);
        $class = $providers[strtolower($marketplaceCode)] ?? null;

        if (!is_string($class) || $class === '') {
            throw new RuntimeException('No read-only API provider is registered for marketplace: '.$marketplaceCode);
        }

        $provider = app($class);
        if (!$provider instanceof MarketplaceReadProvider) {
            throw new RuntimeException('Configured marketplace read provider must implement '.MarketplaceReadProvider::class);
        }

        return $provider;
    }
}
