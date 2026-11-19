<?php

namespace App\Marketplaces\Api;

use App\Models\MarketplaceConnection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Obtains and caches a Flipkart OAuth client-credentials token for one seller connection. */
class FlipkartAccessTokenProvider
{
    public function __construct(private ConnectionTokenCache $tokens) {}

    public function accessToken(MarketplaceConnection $connection): string
    {
        $this->assertConnection($connection);
        $config = config('marketplaces.flipkart', []);
        if (!($config['enabled'] ?? false) || !($config['api_calls_enabled'] ?? false)) {
            throw new ConnectorDisabled('Flipkart API calls are disabled by configuration.');
        }

        $credentials = $connection->credentials ?? [];
        foreach (['app_id', 'app_secret'] as $key) {
            if (empty($credentials[$key])) {
                throw new RuntimeException('Flipkart connection credentials are incomplete: '.$key);
            }
        }

        if ($token = $this->tokens->get($connection, 'flipkart')) {
            return $token;
        }

        $response = Http::withBasicAuth($credentials['app_id'], $credentials['app_secret'])
            ->acceptJson()->connectTimeout(8)->timeout(20)
            ->get('https://api.flipkart.net/oauth-service/oauth/token', [
                'grant_type' => 'client_credentials',
                'scope' => 'Seller_Api,Default',
            ]);

        $token = $response->json('access_token');
        if (!$response->successful() || !is_string($token) || $token === '') {
            throw new RuntimeException('Flipkart token request failed (HTTP '.$response->status().'). Reverify this seller connection.');
        }

        $this->tokens->put($connection, 'flipkart', $token, (int) $response->json('expires_in', 3600));
        return $token;
    }

    private function assertConnection(MarketplaceConnection $connection): void
    {
        $connection->loadMissing('marketplace');
        if (!$connection->exists || $connection->marketplace?->code !== 'flipkart') {
            throw new RuntimeException('A valid Flipkart marketplace connection is required.');
        }
        if ($connection->status !== 'connected') {
            throw new RuntimeException('The Flipkart connection is not in a connected state. Recheck its credentials.');
        }
    }
}
