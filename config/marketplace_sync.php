<?php

return [
    /*
     * Provider classes are intentionally empty by default. Add a provider only after
     * the marketplace grants the required read scopes and its API auth/signing is tested.
     * Example: 'amazon' => App\\Marketplaces\\Amazon\\AmazonReadProvider::class,
     */
    'providers' => [],
    'max_pages_per_run' => (int) env('MARKETPLACE_SYNC_MAX_PAGES', 100),
    'lock_seconds' => (int) env('MARKETPLACE_SYNC_LOCK_SECONDS', 900),
];
