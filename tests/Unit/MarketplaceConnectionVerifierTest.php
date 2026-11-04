<?php

namespace Tests\Unit;

use App\Services\MarketplaceConnectionVerifier;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketplaceConnectionVerifierTest extends TestCase
{
    public function test_amazon_credentials_are_verified_by_lwa_token_exchange(): void
    {
        Http::fake(['api.amazon.com/*' => Http::response(['access_token' => 'token', 'expires_in' => 3600], 200)]);
        $result = app(MarketplaceConnectionVerifier::class)->verify('amazon', [
            'client_id' => 'client', 'client_secret' => 'secret', 'refresh_token' => 'refresh',
        ]);
        $this->assertTrue($result['ok']);
    }

    public function test_amazon_sandbox_verification_checks_lwa_and_static_sandbox_api(): void
    {
        Http::fake([
            'api.amazon.com/*' => Http::response(['access_token' => 'sandbox-access-token', 'expires_in' => 3600], 200),
            'sandbox.sellingpartnerapi-eu.amazon.com/*' => Http::response(['payload' => ['Orders' => []]], 200),
        ]);

        $result = app(MarketplaceConnectionVerifier::class)->verify('amazon', [
            'client_id' => 'sandbox-client',
            'client_secret' => 'sandbox-secret',
            'refresh_token' => 'sandbox-refresh',
            'region' => 'eu',
            'marketplace_id' => 'A21TJRUUN4KGV',
        ], 'sandbox');

        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('mock data only', $result['message']);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'sandbox.sellingpartnerapi-eu.amazon.com/orders/v0/orders')
            && $request->hasHeader('x-amz-access-token', 'sandbox-access-token'));
    }

    public function test_flipkart_credentials_are_verified_by_token_issuance(): void
    {
        Http::fake(['api.flipkart.net/*' => Http::response(['access_token' => 'token'], 200)]);
        $result = app(MarketplaceConnectionVerifier::class)->verify('flipkart', [
            'app_id' => 'app', 'app_secret' => 'secret',
        ]);
        $this->assertTrue($result['ok']);
    }

    public function test_unknown_platform_is_not_falsely_marked_verified(): void
    {
        $result = app(MarketplaceConnectionVerifier::class)->verify('unknown', []);
        $this->assertFalse($result['ok']);
    }
}
