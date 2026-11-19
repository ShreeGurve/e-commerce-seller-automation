<?php

namespace App\Http\Controllers;

use App\Models\{Listing, ListingAttributeMapping, ListingAttributeMappingAudit, Marketplace, MarketplaceAttributeProfile};
use App\Services\MarketplaceAttributeMappingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MarketplaceAttributeMappingController extends Controller
{
    public function index(Request $request)
    {
        $profiles = MarketplaceAttributeProfile::query()->with(['marketplace', 'definitions'])->latest('id')->paginate(20);
        $listings = Listing::query()->with(['product:id,sku,name', 'marketplace:id,name', 'current:id,listing_id,version_no'])
            ->whereNotNull('current_version_id')->latest('id')->limit(100)->get();
        $audits = ListingAttributeMappingAudit::query()->with(['listing.product:id,sku,name', 'listing.marketplace'])->latest('id')->limit(20)->get();
        $pendingMappings = ListingAttributeMapping::query()->with(['listing.product:id,sku,name', 'definition.profile.marketplace'])->where('approval_status', 'PENDING')->latest('id')->limit(50)->get();
        $marketplaces = Marketplace::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        return view('listings.attribute-mapping', compact('profiles', 'listings', 'audits', 'marketplaces', 'pendingMappings'));
    }


    public function workspace(Listing $listing)
    {
        $listing->load(['product.variants', 'marketplace', 'current']);
        $profile = MarketplaceAttributeProfile::query()->with('definitions')->where('marketplace_id', $listing->marketplace_id)
            ->where('category_key', $listing->category_path)->where('is_active', true)->firstOrFail();
        $mappings = ListingAttributeMapping::query()->where('listing_id', $listing->id)->where('listing_version_id', $listing->current_version_id)->get()->keyBy('definition_id');
        return view('listings.attribute-mapping-workspace', compact('listing', 'profile', 'mappings'));
    }

    public function storeProfile(Request $request, MarketplaceAttributeMappingService $service)
    {
        if (is_string($request->input('attributes'))) {
            $decoded = json_decode($request->input('attributes'), true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                return back()->withErrors(['attributes' => 'Attribute definitions must be valid JSON array data.'])->withInput();
            }
            $request->merge(['attributes' => $decoded]);
        }
        $data = $request->validate([
            'marketplace_id' => ['required', 'integer', 'exists:marketplaces,id'],
            'category_key' => ['required', 'string', 'max:120'], 'category_label' => ['required', 'string', 'max:180'],
            'schema_version' => ['nullable', 'string', 'max:60'],
            'attributes' => ['required', 'array', 'min:1'],
            'attributes.*.attribute_key' => ['required', 'string', 'max:120'], 'attributes.*.label' => ['required', 'string', 'max:180'],
            'attributes.*.data_type' => ['required', Rule::in(['TEXT', 'NUMBER', 'BOOLEAN', 'SELECT', 'MULTI_SELECT'])],
            'attributes.*.is_required' => ['nullable', 'boolean'], 'attributes.*.is_variant_attribute' => ['nullable', 'boolean'],
            'attributes.*.allowed_values' => ['nullable', 'array'], 'attributes.*.min_length' => ['nullable', 'integer', 'min:0'],
            'attributes.*.max_length' => ['nullable', 'integer', 'min:1'],
        ]);
        $profile = $service->createProfile($data['marketplace_id'], $data['category_key'], $data['category_label'], $data['attributes'], $data['schema_version'] ?? null);
        return redirect()->route('listings.attributes.index')->with('success', 'Attribute profile saved with '.$profile->definitions->count().' definitions.');
    }

    public function save(Request $request, Listing $listing, MarketplaceAttributeMappingService $service)
    {
        $data = $request->validate(['mappings' => ['required', 'array', 'min:1'], 'mappings.*.definition_id' => ['required', 'integer', 'exists:marketplace_attribute_definitions,id'],
            'mappings.*.source_field' => ['nullable', 'string', 'max:100'], 'mappings.*.value' => ['nullable']]);
        $saved = $service->saveMappings($listing, $data['mappings'], $request->user()?->id);
        return back()->with('success', count($saved).' attribute mapping(s) saved for review.');
    }

    public function validateListing(Request $request, Listing $listing, MarketplaceAttributeMappingService $service)
    {
        $audit = $service->validateListing($listing, $request->user()?->id);
        return back()->with('success', 'Attribute validation '.$audit->status.': '.$audit->missing_required.' required missing, '.$audit->invalid_attributes.' invalid.');
    }

    public function review(Request $request, ListingAttributeMapping $mapping, MarketplaceAttributeMappingService $service)
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['APPROVED', 'REJECTED'])]]);
        $service->reviewMapping($mapping, (int) $request->user()->id, $data['decision']);
        return back()->with('success', 'Attribute mapping '.$data['decision'].'.');
    }
}
