<?php

namespace App\Services;

use App\Models\{Listing, ListingAttributeMapping, ListingAttributeMappingAudit, MarketplaceAttributeDefinition, MarketplaceAttributeProfile};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Configurable category schemas; never represents a live marketplace schema unless explicitly sourced and versioned. */
class MarketplaceAttributeMappingService
{
    private const TYPES = ['TEXT', 'NUMBER', 'BOOLEAN', 'SELECT', 'MULTI_SELECT'];
    private const SOURCES = ['product.sku', 'product.name', 'product.description', 'product.hsn', 'product.product_type', 'product.subcategory', 'product.weight_g', 'product.length_cm', 'product.width_cm', 'product.height_cm', 'listing.brand', 'listing.category_path', 'version.title', 'version.description'];

    public function createProfile(int $marketplaceId, string $categoryKey, string $categoryLabel, array $attributes, ?string $schemaVersion = null): MarketplaceAttributeProfile
    {
        if (!preg_match('/^[A-Za-z0-9._:-]{2,120}$/', $categoryKey)) {
            throw ValidationException::withMessages(['category_key' => 'Use 2–120 letters, numbers, dots, underscores, colons or hyphens.']);
        }
        return DB::transaction(function () use ($marketplaceId, $categoryKey, $categoryLabel, $attributes, $schemaVersion) {
            $profile = MarketplaceAttributeProfile::updateOrCreate(
                ['marketplace_id' => $marketplaceId, 'category_key' => $categoryKey],
                ['category_label' => $categoryLabel, 'schema_source' => 'MANUAL', 'schema_version' => $schemaVersion, 'is_active' => true]
            );
            foreach ($attributes as $index => $attribute) {
                $type = strtoupper($attribute['data_type'] ?? 'TEXT');
                if (!in_array($type, self::TYPES, true)) {
                    throw ValidationException::withMessages(["attributes.{$index}.data_type" => 'Unsupported attribute data type.']);
                }
                MarketplaceAttributeDefinition::updateOrCreate(
                    ['profile_id' => $profile->id, 'attribute_key' => $attribute['attribute_key']],
                    ['label' => $attribute['label'], 'data_type' => $type, 'is_required' => (bool) ($attribute['is_required'] ?? false),
                        'is_variant_attribute' => (bool) ($attribute['is_variant_attribute'] ?? false), 'allowed_values' => $attribute['allowed_values'] ?? null,
                        'min_length' => $attribute['min_length'] ?? null, 'max_length' => $attribute['max_length'] ?? null, 'sort_order' => $index]
                );
            }
            return $profile->load('definitions');
        });
    }

    public function saveMapping(Listing $listing, MarketplaceAttributeDefinition $definition, ?string $sourceField, $value, ?int $userId = null): ListingAttributeMapping
    {
        if ($definition->profile->marketplace_id !== $listing->marketplace_id) {
            throw ValidationException::withMessages(['definition_id' => 'This attribute profile belongs to a different marketplace.']);
        }
        if ($sourceField !== null && !in_array($sourceField, self::SOURCES, true)) {
            throw ValidationException::withMessages(['source_field' => 'Select a supported product or listing source field.']);
        }
        if ($sourceField === null && ($value === null || $value === '' || $value === [])) {
            throw ValidationException::withMessages(['mapped_value' => 'Provide a mapped value or choose a source field.']);
        }
        $versionId = $listing->current_version_id;
        return ListingAttributeMapping::updateOrCreate(
            ['listing_id' => $listing->id, 'listing_version_id' => $versionId, 'definition_id' => $definition->id],
            ['source_field' => $sourceField, 'mapped_value' => $sourceField === null ? ['value' => $value] : null, 'approval_status' => 'PENDING', 'mapped_by' => $userId, 'reviewed_by' => null, 'reviewed_at' => null]
        );
    }

    public function validateListing(Listing $listing, ?int $userId = null): ListingAttributeMappingAudit
    {
        return DB::transaction(function () use ($listing, $userId) {
            $listing = Listing::query()->with(['current', 'product'])->lockForUpdate()->findOrFail($listing->id);
            $profile = MarketplaceAttributeProfile::query()->with('definitions')->where('marketplace_id', $listing->marketplace_id)
                ->where('category_key', $listing->category_path)->where('is_active', true)->first();
            if (!$profile) {
                throw ValidationException::withMessages(['category_path' => 'No active attribute profile matches this listing marketplace and category path. Create or select a category profile first.']);
            }
            $rows = ListingAttributeMapping::query()->where('listing_id', $listing->id)->where('listing_version_id', $listing->current_version_id)->get()->keyBy('definition_id');
            $results = [];
            $valid = $missing = $invalid = 0;
            foreach ($profile->definitions as $definition) {
                $row = $rows->get($definition->id);
                $value = $row?->source_field ? $this->resolveSource($listing, $row->source_field) : ($row?->mapped_value['value'] ?? null);
                $errors = $this->valueErrors($definition, $value);
                if ($row && $row->approval_status !== 'APPROVED') $errors[] = 'Mapping is pending approval or has been rejected.';
                if ($definition->is_variant_attribute) {
                    $variants = $listing->product?->variants ?? collect();
                    if ($variants->isEmpty()) {
                        $errors[] = 'This is marked as a variant attribute, but the product has no active variants.';
                    } else {
                        foreach ($variants as $variant) {
                            $variantAttributes = (array) ($variant->attributes ?? []);
                            if (!array_key_exists($definition->attribute_key, $variantAttributes) || $variantAttributes[$definition->attribute_key] === '' || $variantAttributes[$definition->attribute_key] === null) {
                                $errors[] = 'Variant '.$variant->sku.' is missing this attribute.';
                                break;
                            }
                        }
                    }
                }
                $hasValue = !($value === null || $value === '' || $value === []);
                if (!$hasValue && $definition->is_required) $missing++;
                if ($errors && ($hasValue || !$definition->is_required)) $invalid++;
                elseif ($hasValue) $valid++;
                if (!$hasValue && $definition->is_required) $errors[] = 'Required value is missing.';
                $results[] = ['definition_id' => $definition->id, 'attribute_key' => $definition->attribute_key, 'label' => $definition->label,
                    'required' => $definition->is_required, 'source_field' => $row?->source_field, 'value' => $value, 'approval_status' => $row?->approval_status, 'errors' => $errors];
            }
            $status = ($missing + $invalid) > 0 ? 'REVIEW' : 'VALID';
            return ListingAttributeMappingAudit::create(['listing_id' => $listing->id, 'listing_version_id' => $listing->current_version_id,
                'status' => $status, 'total_attributes' => count($results), 'valid_attributes' => $valid, 'missing_required' => $missing,
                'invalid_attributes' => $invalid, 'results' => $results, 'created_by' => $userId]);
        });
    }

    public function saveMappings(Listing $listing, array $items, ?int $userId = null): array
    {
        $saved = [];
        foreach ($items as $item) {
            $definition = MarketplaceAttributeDefinition::query()->with('profile')->findOrFail($item['definition_id']);
            $saved[] = $this->saveMapping($listing, $definition, $item['source_field'] ?? null, $item['value'] ?? null, $userId);
        }
        return $saved;
    }

    public function validateValue(MarketplaceAttributeDefinition $definition, $value): array
    {
        return $this->valueErrors($definition, $value);
    }

    public function reviewMapping(ListingAttributeMapping $mapping, int $reviewerId, string $decision): ListingAttributeMapping
    {
        if (!in_array($decision, ['APPROVED', 'REJECTED'], true)) throw ValidationException::withMessages(['decision' => 'Invalid review decision.']);
        if ($mapping->mapped_by === $reviewerId) throw ValidationException::withMessages(['mapping' => 'A user cannot approve their own attribute mapping.']);
        $mapping->update(['approval_status' => $decision, 'reviewed_by' => $reviewerId, 'reviewed_at' => now()]);
        return $mapping->refresh();
    }

    private function resolveSource(Listing $listing, string $source)
    {
        [$root, $field] = explode('.', $source, 2);
        $object = match ($root) { 'product' => $listing->product, 'listing' => $listing, 'version' => $listing->current, default => null };
        return $object?->{$field};
    }

    private function valueErrors(MarketplaceAttributeDefinition $definition, $value): array
    {
        if ($value === null || $value === '' || $value === []) return [];
        $errors = [];
        $type = $definition->data_type;
        if ($type === 'NUMBER' && !is_numeric($value)) $errors[] = 'Value must be numeric.';
        if ($type === 'BOOLEAN' && !in_array($value, [true, false, 0, 1, '0', '1'], true)) $errors[] = 'Value must be boolean.';
        if ($type === 'SELECT' && $definition->allowed_values && !in_array($value, $definition->allowed_values, true)) $errors[] = 'Value is not in the allowed options.';
        if ($type === 'MULTI_SELECT') {
            if (!is_array($value)) $errors[] = 'Value must be a list.';
            elseif ($definition->allowed_values && array_diff($value, $definition->allowed_values)) $errors[] = 'One or more values are not in the allowed options.';
        }
        if (in_array($type, ['TEXT', 'SELECT'], true) && is_scalar($value)) {
            $length = mb_strlen((string) $value);
            if ($definition->min_length !== null && $length < $definition->min_length) $errors[] = 'Value is shorter than the minimum length.';
            if ($definition->max_length !== null && $length > $definition->max_length) $errors[] = 'Value exceeds the maximum length.';
        }
        return $errors;
    }
}
