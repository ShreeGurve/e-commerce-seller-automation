<?php

namespace Tests\Unit;

use App\Marketplaces\Api\ConnectorDisabled;
use App\Marketplaces\Api\MarketplaceWriteGate;
use App\Models\ApprovalRequest;
use Tests\TestCase;

class MarketplaceWriteGateTest extends TestCase
{
    public function test_inventory_push_is_disabled_by_default(): void
    {
        config(['marketplaces.push_enabled' => false, 'marketplaces.inventory_auto_sync' => false]);

        $this->expectException(ConnectorDisabled::class);
        app(MarketplaceWriteGate::class)->assertInventoryPushAllowed();
    }

    public function test_inventory_push_requires_both_flags(): void
    {
        config(['marketplaces.push_enabled' => true, 'marketplaces.inventory_auto_sync' => false]);

        $this->expectException(ConnectorDisabled::class);
        app(MarketplaceWriteGate::class)->assertInventoryPushAllowed();
    }

    public function test_price_push_requires_approved_request(): void
    {
        config(['marketplaces.push_enabled' => true]);

        $approval = new ApprovalRequest([
            'status' => 'pending',
            'type' => 'price_change',
            'reviewed_by' => null,
            'reviewed_at' => null,
            'proposed_value' => ['price' => 100],
        ]);

        $this->expectException(\RuntimeException::class);
        app(MarketplaceWriteGate::class)->assertPricePushAllowed($approval);
    }
}
