<?php

namespace App\Marketplaces\Sync;

class MarketplaceReadPage
{
    /** @param array<int, array{external_id:string,payload:array}> $items */
    public function __construct(
        public readonly array $items,
        public readonly ?string $nextCursor = null,
        public readonly bool $hasMore = false,
    ) {}
}
