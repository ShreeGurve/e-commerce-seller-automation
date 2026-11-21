<?php

namespace Tests\Feature;

use App\Marketplaces\Api\AmazonLwaTokenProvider;
use App\Marketplaces\Api\ConnectorDisabled;
use App\Marketplaces\Api\FlipkartAccessTokenProvider;
use App\Marketplaces\Api\MarketplaceConnectionHealthChecker;
use App\Models\Business;
use App\Models\Marketplace;
use App\Models\MarketplaceConnection;
use App\Services\MarketplaceConnectionResolver;
use App\Services\MarketplaceConnectionVerifier;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketplaceConnectionCredentialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['marketplaces.amazon.enabled' => true, 'marketplaces.amazon.api_calls_enabled' => true]);
        config(['marketplaces.flipkart.enabled' => true, 'marketplaces.flipkart.api_calls_enabled' => true]);
    }

    public function test_amazon_token_uses_the_selected_business_connection_and_is_cached_per_connection(): void
    {
        $first = $this->connection('amazon', ['client_id' => 'client-a', 'client_secret' => 'secret-a', 'refresh_token' => 'refresh-a']);
        $second = $this->connection('amazon', ['client_id' => 'client-b', 'client_secret' => 'secret-b', 'refresh_token' => 'refresh-b']);

        Http::fake(function ($request) {
            return Http::response([
                'access_token' => $request['refresh_token'].'-access',
                'expires_in' => 3600,
            ], 200);
        });

        $provider = app(AmazonLwaTokenProvider::class);
        $this->assertSame('refresh-a-access', $provider->accessTokenForConnection($first));
        $this->assertSame('refresh-a-access', $provider->accessTokenForConnection($first));
        $this->assertSame('refresh-b-access', $provider->accessTokenForConnection($second));

        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request['refresh_token'] === 'refresh-a');
        Http::assertSent(fn ($request) => $request['refresh_token'] === 'refresh-b');
    }

    public function test_flipkart_token_uses_and_caches_each_connection_credentials_separately(): void
    {
        $first = $this->connection('flipkart', ['app_id' => 'app-a', 'app_secret' => 'secret-a']);
        $second = $this->connection('flipkart', ['app_id' => 'app-b', 'app_secret' => 'secret-b']);

        $requestNumber = 0;
        Http::fake(function ($request) use (&$requestNumber) {
            $requestNumber++;

            return Http::response([
                'access_token' => $requestNumber === 1 ? 'token-a' : 'token-b',
                'expires_in' => 3600,
            ], 200);
        });

        $provider = app(FlipkartAccessTokenProvider::class);
        $this->assertSame('token-a', $provider->accessToken($first));
        $this->assertSame('token-a', $provider->accessToken($first));
        $this->assertSame('token-b', $provider->accessToken($second));

        Http::assertSentCount(2);
    }

    public function test_connection_resolver_never_returns_another_business_connection(): void
    {
        $connection = $this->connection('amazon', ['client_id' => 'c', 'client_secret' => 's', 'refresh_token' => 'r']);

        $resolved = app(MarketplaceConnectionResolver::class)->resolve(
            $connection->business_id,
            'amazon',
            'production'
        );
        $this->assertSame($connection->id, $resolved->id);

        try {
            app(MarketplaceConnectionResolver::class)->resolve($connection->business_id + 1000, 'amazon', 'production');
            $this->fail('A connection belonging to another business must not be returned.');
        } catch (ModelNotFoundException) {
            $this->assertTrue(true);
        }
    }

    public function test_amazon_token_provider_refuses_calls_when_api_access_is_disabled(): void
    {
        $amazon = $this->connection('amazon', ['client_id' => 'c', 'client_secret' => 's', 'refresh_token' => 'r']);
        config(['marketplaces.amazon.api_calls_enabled' => false]);

        $this->expectException(ConnectorDisabled::class);
        app(AmazonLwaTokenProvider::class)->accessTokenForConnection($amazon);
    }

    public function test_amazon_token_cache_is_invalidated_when_saved_credentials_rotate(): void
    {
        $connection = $this->connection('amazon', ['client_id' => 'client-a', 'client_secret' => 'secret-a', 'refresh_token' => 'refresh-a']);
        $requestNumber = 0;
        Http::fake(function () use (&$requestNumber) {
            $requestNumber++;

            return Http::response(['access_token' => 'token-'.$requestNumber, 'expires_in' => 3600], 200);
        });

        $provider = app(AmazonLwaTokenProvider::class);
        $this->assertSame('token-1', $provider->accessTokenForConnection($connection));

        $connection->update(['credentials' => ['client_id' => 'client-a', 'client_secret' => 'secret-a', 'refresh_token' => 'refresh-rotated']]);
        $connection->refresh();

        $this->assertSame('token-2', $provider->accessTokenForConnection($connection));
        Http::assertSentCount(2);
    }

    public function test_failed_connection_health_check_marks_only_that_connection_for_attention(): void
    {
        $connection = $this->connection('flipkart', ['app_id' => 'app', 'app_secret' => 'secret']);
        $verifier = \Mockery::mock(MarketplaceConnectionVerifier::class);
        $verifier->shouldReceive('verify')->once()->with('flipkart', $connection->credentials, 'production')
            ->andReturn(['ok' => false, 'message' => 'The token could not be issued.']);

        $result = (new MarketplaceConnectionHealthChecker($verifier))->check($connection);

        $this->assertFalse($result['ok']);
        $this->assertSame('attention_required', $connection->fresh()->status);
        $this->assertSame('The token could not be issued.', $connection->fresh()->last_verified_message);
    }

    private function connection(string $code, array $credentials): MarketplaceConnection
    {
        $business = Business::create(['name' => 'Business '.uniqid()]);
        $marketplace = Marketplace::firstOrCreate(['code' => $code], ['name' => ucfirst($code), 'is_active' => true]);

        return MarketplaceConnection::create([
            'business_id' => $business->id,
            'marketplace_id' => $marketplace->id,
            'environment' => 'production',
            'account_label' => 'Test seller',
            'credentials' => $credentials,
            'status' => 'connected',
            'verified_at' => now(),
            'last_verified_at' => now(),
        ]);
    }
}
