<?php

namespace App\Marketplaces\Api;

use App\Models\MarketplaceConnection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Obtains Amazon Login With Amazon access tokens.
 * The connection-aware method uses the encrypted seller connection rather than global .env secrets.
 * Current SP-API requests use the LWA access token in x-amz-access-token. AWS IAM and
 * Signature Version 4 have not been required for SP-API since October 2, 2023.
 */
class AmazonLwaTokenProvider
{
    public function __construct(private ?ConnectionTokenCache $connectionTokens = null) {}

    /** Legacy environment-based token method retained for existing callers. */
    public function accessToken(): string
    {
        $cfg = config('marketplaces.amazon', []);
        $this->assertApiEnabled($cfg);
        foreach (['client_id', 'client_secret', 'refresh_token'] as $key) {
            if (empty($cfg[$key])) {
                throw new RuntimeException('Amazon LWA configuration is incomplete: '.$key);
            }
        }

        return Cache::remember('marketplaces.amazon.lwa_token', now()->addMinutes(50), function () use ($cfg) {
            return $this->requestToken($cfg['client_id'], $cfg['client_secret'], $cfg['refresh_token']);
        });
    }

    /** Returns an LWA token for the credentials encrypted on this exact business connection. */
    public function accessTokenForConnection(MarketplaceConnection $connection): string
    {
        $connection->loadMissing('marketplace');
        if (!$connection->exists || $connection->marketplace?->code !== 'amazon') {
            throw new RuntimeException('A valid Amazon marketplace connection is required.');
        }
        if ($connection->status !== 'connected') {
            throw new RuntimeException('The Amazon connection is not in a connected state. Recheck its credentials.');
        }

        $cfg = config('marketplaces.amazon', []);
        $this->assertApiEnabled($cfg);
        $credentials = $connection->credentials ?? [];
        foreach (['client_id', 'client_secret', 'refresh_token'] as $key) {
            if (empty($credentials[$key])) {
                throw new RuntimeException('Amazon connection credentials are incomplete: '.$key);
            }
        }

        $cache = $this->connectionTokens ?? app(ConnectionTokenCache::class);
        if ($token = $cache->get($connection, 'amazon-lwa')) {
            return $token;
        }

        $response = Http::asForm()->acceptJson()->connectTimeout(8)->timeout(20)
            ->post('https://api.amazon.com/auth/o2/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $credentials['refresh_token'],
                'client_id' => $credentials['client_id'],
                'client_secret' => $credentials['client_secret'],
            ]);

        $token = $response->json('access_token');
        if (!$response->successful() || !is_string($token) || $token === '') {
            throw new RuntimeException('Amazon LWA token request failed (HTTP '.$response->status().'). Reverify this seller connection.');
        }

        $cache->put($connection, 'amazon-lwa', $token, (int) $response->json('expires_in', 3600));
        return $token;
    }

    private function requestToken(string $clientId, string $clientSecret, string $refreshToken): string
    {
        $response = Http::asForm()->acceptJson()->connectTimeout(8)->timeout(20)
            ->post('https://api.amazon.com/auth/o2/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);

        $token = $response->json('access_token');
        if (!$response->successful() || !is_string($token) || $token === '') {
            throw new RuntimeException('Amazon LWA token request failed (HTTP '.$response->status().'). Check the seller authorization.');
        }

        return $token;
    }

    private function assertApiEnabled(array $config): void
    {
        if (!($config['enabled'] ?? false) || !($config['api_calls_enabled'] ?? false)) {
            throw new ConnectorDisabled('Amazon API calls are disabled by configuration.');
        }
    }
}
