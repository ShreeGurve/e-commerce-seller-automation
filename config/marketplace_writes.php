<?php

return [
    // Two independent environment gates. Keep false until each seller connection and operation is reviewed.
    'enabled' => (bool) env('MARKETPLACE_WRITES_ENABLED', false),
    'amazon_enabled' => (bool) env('AMAZON_SP_API_WRITE_ENABLED', false),
    'flipkart_enabled' => (bool) env('FLIPKART_SELLER_API_WRITE_ENABLED', false),
    'attempt_ttl_minutes' => (int) env('MARKETPLACE_WRITE_ATTEMPT_TTL_MINUTES', 30),
];
