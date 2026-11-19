<?php

namespace App\Marketplaces\Api;

use App\Models\MarketplaceConnection;
use Illuminate\Support\Facades\Cache;

/** Keeps short-lived access tokens isolated by connection and credential revision. */
class ConnectionTokenCache
{
    public function get(MarketplaceConnection $connection, string $provider): ?string
    {
        return Cache::get($this->key($connection, $provider));
    }

    public function put(MarketplaceConnection $connection, string $provider, string $token, int $expiresIn): void
    {
        $ttl = max(60, $expiresIn - 120);
        Cache::put($this->key($connection, $provider), $token, now()->addSeconds($ttl));
    }

    public function forget(MarketplaceConnection $connection, string $provider): void
    {
        Cache::forget($this->key($connection, $provider));
    }

    private function key(MarketplaceConnection $connection, string $provider): string
    {
        // A one-way fingerprint prevents stale tokens after credential rotation without exposing secrets.
        $credentials = $connection->credentials ?? [];
        $revision = hash('sha256', json_encode($credentials, JSON_UNESCAPED_SLASHES) ?: 'empty');
        return implode(':', ['marketplace', 'connection-token', $provider, $connection->business_id, $connection->id, $revision]);
    }
}
