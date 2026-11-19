<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketplace_attribute_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_id')->constrained('marketplaces')->cascadeOnDelete();
            $table->string('category_key', 120);
            $table->string('category_label', 180);
            $table->string('schema_source', 40)->default('MANUAL');
            $table->string('schema_version', 60)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['marketplace_id', 'category_key'], 'map_profiles_market_cat_uq');
        });

        Schema::create('marketplace_attribute_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('marketplace_attribute_profiles', 'id', 'map_defs_profile_fk')->cascadeOnDelete();
            $table->string('attribute_key', 120);
            $table->string('label', 180);
            $table->string('data_type', 20)->default('TEXT');
            $table->boolean('is_required')->default(false);
            $table->boolean('is_variant_attribute')->default(false);
            $table->json('allowed_values')->nullable();
            $table->unsignedInteger('min_length')->nullable();
            $table->unsignedInteger('max_length')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['profile_id', 'attribute_key'], 'map_defs_profile_key_uq');
        });

        Schema::create('listing_attribute_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->foreignId('listing_version_id')->nullable()->constrained('listing_versions')->nullOnDelete();
            $table->foreignId('definition_id')->constrained('marketplace_attribute_definitions', 'id', 'map_rows_definition_fk')->cascadeOnDelete();
            $table->string('source_field', 100)->nullable();
            $table->json('mapped_value')->nullable();
            $table->string('approval_status', 16)->default('PENDING');
            $table->foreignId('mapped_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['listing_id', 'listing_version_id', 'definition_id'], 'map_rows_listing_ver_def_uq');
            $table->index(['listing_id', 'approval_status'], 'map_rows_listing_status_idx');
        });

        Schema::create('listing_attribute_mapping_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->foreignId('listing_version_id')->nullable()->constrained('listing_versions')->nullOnDelete();
            $table->string('status', 16);
            $table->unsignedInteger('total_attributes')->default(0);
            $table->unsignedInteger('valid_attributes')->default(0);
            $table->unsignedInteger('missing_required')->default(0);
            $table->unsignedInteger('invalid_attributes')->default(0);
            $table->json('results')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['listing_id', 'created_at'], 'map_audits_listing_created_idx');
        });

        if (Schema::hasTable('permissions')) {
            $now = now();
            DB::table('permissions')->insertOrIgnore([
                ['name' => 'listings.attributes.view', 'module' => 'listings', 'description' => 'View marketplace attribute mappings', 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'listings.attributes.manage', 'module' => 'listings', 'description' => 'Manage marketplace attribute mappings', 'created_at' => $now, 'updated_at' => $now],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_attribute_mapping_audits');
        Schema::dropIfExists('listing_attribute_mappings');
        Schema::dropIfExists('marketplace_attribute_definitions');
        Schema::dropIfExists('marketplace_attribute_profiles');
    }
};
