<?php

namespace App\Marketplaces\Sync;

use App\Models\MarketplaceConnection;
use DateTimeInterface;

/**
 * Read-only provider contract. Implementations must only call marketplace GET/read endpoints.
 * Each item must be ['external_id' => string, 'payload' => array].
 */
interface MarketplaceReadProvider
{
    public function fetchPage(
        MarketplaceConnection $connection,
        string $resource,
        ?DateTimeInterface $from,
        ?DateTimeInterface $to,
        ?string $cursor
    ): MarketplaceReadPage;
}
