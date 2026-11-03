<?php

namespace App\Marketplaces\Sync;

/**
 * Read-only connector diagnostics. Never returns credential values or calls external APIs.
 */
class ConnectorReadiness
{
    public function status(): array
    {
        $amazon = config('marketplaces.amazon', []);
        $flipkart = config('marketplaces.flipkart', []);

        $amazonConfigured = $this->allPresent($amazon, [
            'endpoint', 'region', 'marketplace_id', 'client_id', 'client_secret', 'refresh_token',
        ]);
        $flipkartConfigured = $this->allPresent($flipkart, [
            'base_url', 'seller_id', 'access_token',
        ]);

        return [
            'push_enabled' => (bool) config('marketplaces.push_enabled', false),
            'inventory_auto_sync' => (bool) config('marketplaces.inventory_auto_sync', false),
            'amazon' => [
                'enabled' => (bool) ($amazon['enabled'] ?? false),
                'configured' => $amazonConfigured,
                'api_calls_enabled' => (bool) ($amazon['api_calls_enabled'] ?? false) && (bool) ($amazon['enabled'] ?? false),
                'status' => !($amazon['enabled'] ?? false) ? 'NOT_CONFIGURED' : (!$amazonConfigured ? 'PENDING_CREDENTIALS' : (($amazon['api_calls_enabled'] ?? false) ? 'READY_FOR_AUTH_CHECK' : 'DISABLED')),
                'missing' => $this->missing($amazon, ['endpoint', 'region', 'marketplace_id', 'client_id', 'client_secret', 'refresh_token']),
                'message' => 'Amazon SP-API requires a registered application, seller authorization, operation roles and regional request signing.',
            ],
            'flipkart' => [
                'enabled' => (bool) ($flipkart['enabled'] ?? false),
                'configured' => $flipkartConfigured,
                'api_calls_enabled' => (bool) ($flipkart['api_calls_enabled'] ?? false) && (bool) ($flipkart['enabled'] ?? false),
                'status' => !($flipkart['enabled'] ?? false) ? 'NOT_CONFIGURED' : (!$flipkartConfigured ? 'PENDING_CREDENTIALS' : (($flipkart['api_calls_enabled'] ?? false) ? 'READY_FOR_AUTH_CHECK' : 'DISABLED')),
                'missing' => $this->missing($flipkart, ['base_url', 'seller_id', 'access_token']),
                'message' => 'Flipkart Seller API requires approved Seller Dashboard developer access and seller-specific credentials.',
            ],
            'write_policy' => [
                'push_enabled' => (bool) config('marketplaces.push_enabled', false),
                'inventory_push_enabled' => (bool) config('marketplaces.push_enabled', false) && (bool) config('marketplaces.inventory_auto_sync', false),
                'price_push_requires_approval' => true,
                'live_writes_blocked_until_api_implementation' => true,
            ],
        ];
    }

    private function allPresent(array $config, array $keys): bool
    {
        return $this->missing($config, $keys) === [];
    }

    private function missing(array $config, array $keys): array
    {
        return array_values(array_filter($keys, fn ($key) => !isset($config[$key]) || trim((string) $config[$key]) === ''));
    }
}
