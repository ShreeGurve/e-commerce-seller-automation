<?php

namespace App\Marketplaces\Api;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MarketplaceHttpClient
{
    public function request(string $baseUrl, array $headers = [], int $timeout = 20): PendingRequest
    {
        $baseUrl = trim($baseUrl);
        if (!filter_var($baseUrl, FILTER_VALIDATE_URL) || !in_array(parse_url($baseUrl, PHP_URL_SCHEME), ['https'], true)) {
            throw new RuntimeException('Marketplace API base URL must be a valid HTTPS URL.');
        }

        return Http::baseUrl(rtrim($baseUrl, '/'))
            ->acceptJson()
            ->asJson()
            ->withHeaders($headers)
            ->connectTimeout(8)
            ->timeout($timeout)
            ->retry([250, 750, 1500], throw: false);
    }
}
