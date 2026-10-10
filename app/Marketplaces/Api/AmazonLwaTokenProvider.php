<?php

namespace App\Marketplaces\Api;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Obtains an Amazon Login With Amazon access token.
 * This is not an SP-API request signer: SP-API calls also require the correct
 * regional endpoint and, for the relevant authorization model, AWS SigV4.
 */
class AmazonLwaTokenProvider
{
    public function accessToken(): string
    {
        $cfg = config('marketplaces.amazon', []);
        if (!($cfg['enabled'] ?? false) || !($cfg['api_calls_enabled'] ?? false)) {
            throw new ConnectorDisabled('Amazon API calls are disabled by configuration.');
        }

        foreach (['client_id', 'client_secret', 'refresh_token'] as $key) {
            if (empty($cfg[$key])) {
                throw new RuntimeException('Amazon LWA configuration is incomplete: ' . $key);
            }
        }

        return Cache::remember('marketplaces.amazon.lwa_token', now()->addMinutes(50), function () use ($cfg) {
            $response = Http::asForm()
                ->acceptJson()
                ->connectTimeout(8)
                ->timeout(20)
                ->post('https://api.amazon.com/auth/o2/token', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $cfg['refresh_token'],
                    'client_id' => $cfg['client_id'],
                    'client_secret' => $cfg['client_secret'],
                ]);

            if (!$response->successful() || !is_string($response->json('access_token'))) {
                throw new RuntimeException('Amazon LWA token request failed (HTTP ' . $response->status() . '). Check local configuration and seller authorization.');
            }

            return $response->json('access_token');
        });
    }
}
