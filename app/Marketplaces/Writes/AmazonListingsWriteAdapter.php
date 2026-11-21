<?php

namespace App\Marketplaces\Writes;

use App\Marketplaces\Api\AmazonLwaTokenProvider;
use App\Models\MarketplaceConnection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AmazonListingsWriteAdapter
{
    public function __construct(private AmazonLwaTokenProvider $tokens) {}

    public function submit(MarketplaceConnection $connection, array $payload): array
    {
        $credentials = $connection->credentials ?? [];
        $region = strtolower((string) ($credentials['region'] ?? 'eu'));
        $environment = $connection->environment ?? 'production';
        $base = $this->endpoint($region, $environment);
        $sellerId = (string) ($credentials['seller_id'] ?? '');
        $marketplaceId = (string) ($credentials['marketplace_id'] ?? '');
        $sku = (string) ($payload['sku'] ?? '');

        if ($sellerId === '' || $marketplaceId === '' || $sku === '') {
            throw new RuntimeException('Amazon seller ID, marketplace ID and mapped SKU are required.');
        }
        if ($environment !== 'production') {
            throw new RuntimeException('Live writes are permitted only on a verified production connection.');
        }

        $token = $this->tokens->accessTokenForConnection($connection);
        $url = $base.'/listings/2021-08-01/items/'.rawurlencode($sellerId).'/'.rawurlencode($sku).'?'.http_build_query(['marketplaceIds' => $marketplaceId]);
        $response = Http::withHeaders([
            'x-amz-access-token' => $token,
            'User-Agent' => 'SellerAutomation/1.0 (Language=PHP)',
            'Content-Type' => 'application/json',
        ])->acceptJson()->asJson()->connectTimeout(8)->timeout(30)->patch($url, [
            'productType' => $payload['product_type'],
            'patches' => $payload['patches'],
        ]);

        $body = $response->json() ?? ['raw' => mb_substr($response->body(), 0, 4000)];
        $accepted = $response->successful() && is_array($body) && strtoupper((string) ($body['status'] ?? '')) === 'ACCEPTED';

        return [
            'http_status' => $response->status(),
            'accepted' => $accepted,
            'body' => is_array($body) ? $this->safeBody($body) : ['raw' => mb_substr($response->body(), 0, 4000)],
            'message' => $accepted
                ? 'Amazon accepted the Listings Items submission. Acceptance is not confirmation that the listing change is live; check listing status and issues.'
                : 'Amazon did not accept the Listings Items submission (HTTP '.$response->status().', status '.(is_array($body) ? (string) ($body['status'] ?? 'missing') : 'missing').').',
        ];
    }

    private function endpoint(string $region, string $environment): string
    {
        $suffix = match ($region) {
            'na' => 'na', 'fe' => 'fe', 'eu' => 'eu',
            default => throw new RuntimeException('Amazon region must be na, eu or fe.'),
        };
        $host = $environment === 'sandbox'
            ? 'https://sandbox.sellingpartnerapi-'.$suffix.'.amazon.com'
            : 'https://sellingpartnerapi-'.$suffix.'.amazon.com';

        return $host;
    }

    private function safeBody(array $body): array
    {
        unset($body['access_token'], $body['refresh_token'], $body['client_secret'], $body['authorization']);

        return $body;
    }
}
