<?php

namespace Tests\Unit;

use App\Marketplaces\Sync\ConnectorReadiness;
use Tests\TestCase;

class ConnectorReadinessTest extends TestCase
{
    public function test_marketplace_api_calls_remain_disabled_by_default(): void
    {
        config(['marketplaces.push_enabled' => false, 'marketplaces.inventory_auto_sync' => false]);
        config(['marketplaces.amazon' => ['enabled' => false, 'client_id' => null, 'client_secret' => null, 'refresh_token' => null, 'marketplace_id' => null]]);
        config(['marketplaces.flipkart' => ['enabled' => false, 'base_url' => null, 'seller_id' => null, 'access_token' => null]]);

        $status = app(ConnectorReadiness::class)->status();

        $this->assertFalse($status['push_enabled']);
        $this->assertFalse($status['inventory_auto_sync']);
        $this->assertFalse($status['amazon']['configured']);
        $this->assertFalse($status['amazon']['api_calls_enabled']);
        $this->assertFalse($status['flipkart']['configured']);
        $this->assertFalse($status['flipkart']['api_calls_enabled']);
        $this->assertArrayNotHasKey('client_secret', $status['amazon']);
        $this->assertArrayNotHasKey('access_token', $status['flipkart']);
    }
}
