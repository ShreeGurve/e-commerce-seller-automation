<?php

namespace App\Marketplaces\Api;

use App\Models\ApprovalRequest;
use RuntimeException;

/**
 * Central guard for future marketplace writes. Keep writes disabled until the
 * relevant marketplace operation has been implemented and seller access tested.
 */
class MarketplaceWriteGate
{
    public function assertInventoryPushAllowed(): void
    {
        if (!config('marketplaces.push_enabled', false)
            || !config('marketplaces.inventory_auto_sync', false)) {
            throw new ConnectorDisabled('Marketplace inventory push is disabled.');
        }
    }

    public function assertPricePushAllowed(ApprovalRequest $approval): void
    {
        if (!config('marketplaces.push_enabled', false)) {
            throw new ConnectorDisabled('Marketplace pushes are disabled.');
        }

        if (!in_array(strtoupper((string) $approval->status), ['APPROVED'], true)) {
            throw new RuntimeException('Price push requires an APPROVED approval request.');
        }

        if (!in_array(strtolower((string) ($approval->type ?: $approval->request_type)), ['price_change', 'price-change', 'price'], true)) {
            throw new RuntimeException('Approval request is not a price-change request.');
        }

        if (!$approval->reviewed_by || !$approval->reviewed_at) {
            throw new RuntimeException('Price approval must include reviewer and review timestamp.');
        }

        if (!$approval->proposed_value) {
            throw new RuntimeException('Approved price request has no proposed value.');
        }
    }
}
