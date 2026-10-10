<?php

namespace App\Marketplaces\Sync;

/** Reports readiness without exposing credential values. Does not call external APIs. */
class ConnectorReadiness
{
    public function status(): array
    {
        $amazon = config('marketplaces.amazon', []);
        $flipkart = config('marketplaces.flipkart', []);

        return [
            'push_enabled' => (bool) config('marketplaces.push_enabled', false),
            'inventory_auto_sync' => (bool) config('marketplaces.inventory_auto_sync', false),
            'write_policy' => [
                'push_enabled' => (bool) config('marketplaces.push_enabled', false),
                'inventory_push_enabled' => (bool) config('marketplaces.push_enabled', false) && (bool) config('marketplaces.inventory_auto_sync', false),
                'price_push_requires_approval' => true,
            ],
            'amazon' => [
                'enabled' => (bool) ($amazon['enabled'] ?? false),
                'configured' => $this->allPresent($amazon, ['client_id', 'client_secret', 'refresh_token', 'marketplace_id']),
                'api_calls_enabled' => (bool) ($amazon['api_calls_enabled'] ?? false) && (bool) ($amazon['enabled'] ?? false),
                'message' => 'Amazon SP-API connector groundwork only; authorization and operation-specific roles are not configured.',
            ],
            'flipkart' => [
                'enabled' => (bool) ($flipkart['enabled'] ?? false),
                'configured' => $this->allPresent($flipkart, ['base_url', 'seller_id', 'access_token']),
                'api_calls_enabled' => (bool) ($flipkart['api_calls_enabled'] ?? false) && (bool) ($flipkart['enabled'] ?? false),
                'message' => 'Flipkart Seller API connector groundwork only; seller API access is not configured.',
            ],
        ];
    }

    private function allPresent(array $config, array $keys): bool
    {
        foreach ($keys as $key) {
            if (!isset($config[$key]) || trim((string) $config[$key]) === '') return false;
        }
        return true;
    }
}
