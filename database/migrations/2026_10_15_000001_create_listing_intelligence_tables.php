<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->foreignId('marketplace_id')->constrained()->restrictOnDelete();
            $t->string('external_listing_id', 120)->nullable();
            $t->string('title', 500);
            $t->json('bullet_points')->nullable();
            $t->longText('description')->nullable();
            $t->text('backend_keywords')->nullable();
            $t->json('special_features')->nullable();
            $t->boolean('has_a_plus_content')->default(false);
            $t->json('image_urls')->nullable();
            $t->string('status', 30)->default('draft')->index();
            $t->unsignedTinyInteger('score')->default(0);
            $t->json('score_breakdown')->nullable();
            $t->timestamps();
            $t->index(['product_id', 'marketplace_id']);
            $t->unique(['marketplace_id', 'external_listing_id']);
        });
        Schema::create('listing_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('version');
            $t->json('snapshot');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['listing_id', 'version']);
        });
        Schema::create('keywords', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('marketplace_id')->nullable()->constrained()->nullOnDelete();
            $t->string('keyword', 255);
            $t->string('keyword_type', 30)->default('primary');
            $t->unsignedInteger('search_volume')->nullable();
            $t->string('competition', 30)->nullable();
            $t->unsignedTinyInteger('relevance')->nullable();
            $t->unsignedInteger('ranking')->nullable();
            $t->string('source', 100)->nullable();
            $t->string('group_name', 120)->nullable();
            $t->timestamps();
            $t->index(['product_id', 'marketplace_id']);
            $t->index('keyword');
        });
        Schema::create('keyword_rankings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('keyword_id')->constrained()->cascadeOnDelete();
            $t->date('observed_on');
            $t->unsignedInteger('ranking')->nullable();
            $t->string('source', 100)->nullable();
            $t->timestamps();
            $t->unique(['keyword_id', 'observed_on']);
        });
        Schema::create('price_history', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->decimal('selling_price', 15, 2);
            $t->decimal('break_even_price', 15, 2);
            $t->decimal('target_price', 15, 2);
            $t->decimal('estimated_unit_cost', 15, 2)->default(0);
            $t->decimal('marketplace_fee_percent', 7, 3)->default(0);
            $t->decimal('ad_cost_percent', 7, 3)->default(0);
            $t->decimal('target_margin_percent', 7, 3)->default(0);
            $t->date('effective_from');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['product_id', 'effective_from']);
        });
        // Add permissions safely for existing installations without re-syncing customized roles.
        $now = now();
        DB::table('permissions')->insertOrIgnore([['name' => 'keywords.view', 'module' => 'keywords', 'description' => 'View keyword research', 'created_at' => $now, 'updated_at' => $now], ['name' => 'keywords.manage', 'module' => 'keywords', 'description' => 'Manage keyword research', 'created_at' => $now, 'updated_at' => $now], ['name' => 'pricing.view', 'module' => 'pricing', 'description' => 'View pricing intelligence', 'created_at' => $now, 'updated_at' => $now], ['name' => 'pricing.manage', 'module' => 'pricing', 'description' => 'Calculate and save pricing analysis', 'created_at' => $now, 'updated_at' => $now]]);
        $permissionIds = DB::table('permissions')->whereIn('name', ['listings.view', 'listings.manage', 'keywords.view', 'keywords.manage', 'pricing.view', 'pricing.manage'])->pluck('id', 'name');
        foreach (DB::table('roles')->whereIn('slug', ['super-admin', 'admin', 'marketing-manager'])->pluck('id') as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
            }
        }
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('name', ['keywords.view', 'keywords.manage', 'pricing.view', 'pricing.manage'])->delete();
        Schema::dropIfExists('price_history');
        Schema::dropIfExists('keyword_rankings');
        Schema::dropIfExists('keywords');
        Schema::dropIfExists('listing_versions');
        Schema::dropIfExists('listings');
    }
};
