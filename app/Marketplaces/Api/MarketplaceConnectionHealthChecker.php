<?php

namespace App\Marketplaces\Api;

use App\Models\MarketplaceConnection;
use App\Services\MarketplaceConnectionVerifier;

/** Revalidates the encrypted credentials already saved for a single connection. */
class MarketplaceConnectionHealthChecker
{
    public function __construct(private MarketplaceConnectionVerifier $verifier) {}

    public function check(MarketplaceConnection $connection): array
    {
        $connection->loadMissing('marketplace');
        $result = $this->verifier->verify(
            (string) $connection->marketplace?->code,
            $connection->credentials ?? [],
            $connection->environment ?? 'production'
        );

        $connection->forceFill([
            'status' => $result['ok'] ? 'connected' : 'attention_required',
            'last_verified_at' => now(),
            'last_verified_message' => $result['message'],
            'verified_at' => $result['ok'] ? now() : $connection->verified_at,
        ])->save();

        return $result;
    }
}
