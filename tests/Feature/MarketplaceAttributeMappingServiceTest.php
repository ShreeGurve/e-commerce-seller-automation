<?php

namespace Tests\Feature;

use App\Models\MarketplaceAttributeDefinition;
use App\Services\MarketplaceAttributeMappingService;
use Tests\TestCase;

class MarketplaceAttributeMappingServiceTest extends TestCase
{
    public function test_number_attributes_reject_non_numeric_values(): void
    {
        $definition = new MarketplaceAttributeDefinition(['data_type' => 'NUMBER']);
        $service = app(MarketplaceAttributeMappingService::class);

        $this->assertSame([], $service->validateValue($definition, '750'));
        $this->assertContains('Value must be numeric.', $service->validateValue($definition, 'seven hundred'));
    }

    public function test_select_attributes_enforce_configured_allowed_values(): void
    {
        $definition = new MarketplaceAttributeDefinition(['data_type' => 'SELECT', 'allowed_values' => ['Black', 'Blue']]);
        $service = app(MarketplaceAttributeMappingService::class);

        $this->assertSame([], $service->validateValue($definition, 'Black'));
        $this->assertContains('Value is not in the allowed options.', $service->validateValue($definition, 'Red'));
    }

    public function test_multi_select_attributes_require_a_list_of_allowed_values(): void
    {
        $definition = new MarketplaceAttributeDefinition(['data_type' => 'MULTI_SELECT', 'allowed_values' => ['Cotton', 'Polyester']]);
        $service = app(MarketplaceAttributeMappingService::class);

        $this->assertSame([], $service->validateValue($definition, ['Cotton', 'Polyester']));
        $this->assertContains('Value must be a list.', $service->validateValue($definition, 'Cotton'));
        $this->assertContains('One or more values are not in the allowed options.', $service->validateValue($definition, ['Silk']));
    }

    public function test_text_attributes_enforce_minimum_and_maximum_lengths(): void
    {
        $definition = new MarketplaceAttributeDefinition(['data_type' => 'TEXT', 'min_length' => 3, 'max_length' => 6]);
        $service = app(MarketplaceAttributeMappingService::class);

        $this->assertSame([], $service->validateValue($definition, 'Bottle'));
        $this->assertContains('Value is shorter than the minimum length.', $service->validateValue($definition, 'AB'));
        $this->assertContains('Value exceeds the maximum length.', $service->validateValue($definition, 'Bottles'));
    }

    public function test_boolean_attributes_accept_boolean_representations_only(): void
    {
        $definition = new MarketplaceAttributeDefinition(['data_type' => 'BOOLEAN']);
        $service = app(MarketplaceAttributeMappingService::class);

        $this->assertSame([], $service->validateValue($definition, true));
        $this->assertContains('Value must be boolean.', $service->validateValue($definition, 'yes'));
    }
}
