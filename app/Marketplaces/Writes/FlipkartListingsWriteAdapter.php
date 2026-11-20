<?php

namespace App\Marketplaces\Writes;

use App\Marketplaces\Api\FlipkartAccessTokenProvider;
use App\Models\MarketplaceConnection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FlipkartListingsWriteAdapter
{
    public function __construct(private FlipkartAccessTokenProvider $tokens) {}

    public function submit(MarketplaceConnection $connection, array $payload): array
    {
        if (($connection->environment ?? 'production') !== 'production') {
            throw new RuntimeException('Flipkart write adapter currently supports production connections only.');
        }

        $token = $this->tokens->accessToken($connection);
        $sku = (string) ($payload['sku'] ?? '');
        if ($sku === '') {
            throw new RuntimeException('A mapped Flipkart seller SKU is required.');
        }

        $path = match ($payload['operation'] ?? '') {
            'PRICE_UPDATE' => '/listings/v3/update/price',
            'INVENTORY_UPDATE' => '/listings/v3/update/inventory',
            default => throw new RuntimeException('Unsupported Flipkart listing write operation.'),
        };

        // Flipkart's Listing Management v3 accepts a SKU-keyed payload; this package sends one SKU per request.
        $body = [$sku => $payload['marketplace_payload']];
        $response = Http::withToken($token)->acceptJson()->asJson()
            ->connectTimeout(8)->timeout(30)
            ->post('https://api.flipkart.net/sellers'.$path, $body);
        $decoded = $response->json();
        $skuResult = is_array($decoded) ? ($decoded[$sku] ?? null) : null;
        $skuStatus = strtolower((string) (is_array($skuResult) ? ($skuResult['status'] ?? '') : ''));
        $accepted = $response->successful() && in_array($skuStatus, ['success', 'warning'], true);

        return [
            'http_status' => $response->status(),
            'accepted' => $accepted,
            'sku_status' => $skuStatus ?: null,
            'body' => is_array($decoded) ? $decoded : ['raw' => mb_substr($response->body(), 0, 4000)],
            'message' => $accepted
                ? 'Flipkart accepted the SKU update response. Review any warnings and verify the listing after processing.'
                : 'Flipkart did not confirm success for this SKU (HTTP '.$response->status().').',
        ];
    }
}
