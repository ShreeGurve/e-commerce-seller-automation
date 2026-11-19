<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MarketplaceConnectionEnvironmentSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketplace_connections_support_separate_environment_per_business_and_marketplace(): void
    {
        $this->assertTrue(Schema::hasColumn('marketplace_connections', 'environment'));

        $indexes = collect(Schema::getIndexes('marketplace_connections'));

        $this->assertTrue($indexes->contains(fn (array $index): bool =>
            ($index['unique'] ?? false) === true
            && ($index['columns'] ?? []) === ['business_id', 'marketplace_id', 'environment']
        ), 'The business + marketplace + environment unique index must exist.');

        $this->assertTrue($indexes->contains(fn (array $index): bool =>
            ($index['columns'] ?? []) === ['business_id']
        ), 'The business foreign key must retain a supporting index.');
    }
}
