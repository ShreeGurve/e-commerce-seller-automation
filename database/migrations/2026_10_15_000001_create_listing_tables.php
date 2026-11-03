<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // One listing per product per marketplace. Version content is stored
        // separately so edits never overwrite an earlier listing draft.
        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('marketplace_id')->constrained()->restrictOnDelete();
            $table->string('external_listing_id', 120)->nullable();
            $table->string('status', 12)->default('DRAFT'); // DRAFT | LIVE | SUPPRESSED | INACTIVE
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->unsignedBigInteger('published_version_id')->nullable();
            $table->string('category_path')->nullable();
            $table->string('brand', 120)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['product_id', 'marketplace_id'], 'listings_product_marketplace_unique');
            $table->unique(['marketplace_id', 'external_listing_id'], 'listings_marketplace_external_unique');
        });

        Schema::create('listing_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->unsignedInteger('version_no');
            $table->string('title', 500);
            $table->json('bullets')->nullable();
            $table->longText('description')->nullable();
            $table->string('backend_keywords', 500)->nullable();
            $table->text('special_features')->nullable();
            $table->longText('aplus_content')->nullable();
            $table->unsignedSmallInteger('image_count')->default(0);
            $table->boolean('has_video')->default(false);
            $table->decimal('score', 5, 2)->default(0);
            $table->json('score_breakdown')->nullable();
            $table->string('change_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['listing_id', 'version_no'], 'listing_versions_listing_version_unique');
        });

        Schema::create('listing_score_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_id')->nullable()->constrained('marketplaces')->cascadeOnDelete();
            $table->string('criterion', 20);
            $table->decimal('max_points', 5, 2);
            $table->json('params')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['marketplace_id', 'criterion'], 'listing_score_rules_marketplace_criterion_unique');
        });

        $now = now();
        $defaults = [
            ['title', 20, ['min_len' => 80, 'max_len' => 200, 'front_chars' => 80]],
            ['bullets', 20, ['count' => 5, 'min_len' => 100, 'max_len' => 500]],
            ['description', 10, ['min_len' => 1000, 'max_len' => 2000]],
            ['keywords', 15, []],
            ['images', 15, ['target' => 7]],
            ['aplus', 10, ['min_len' => 200]],
            ['features', 5, ['target' => 3]],
            ['backend', 5, ['min_bytes' => 150, 'max_bytes' => 250]],
        ];
        foreach ($defaults as [$criterion, $points, $params]) {
            DB::table('listing_score_rules')->insertOrIgnore([
                'marketplace_id' => null,
                'criterion' => $criterion,
                'max_points' => $points,
                'params' => json_encode($params),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::create('keyword_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('marketplace_id')->constrained('marketplaces')->restrictOnDelete();
            $table->string('keyword', 255);
            $table->char('key_hash', 40);
            $table->string('keyword_type', 12)->default('SECONDARY');
            $table->unsignedInteger('search_volume')->nullable();
            $table->string('competition', 8)->nullable();
            $table->unsignedTinyInteger('relevance')->nullable();
            $table->unsignedInteger('current_rank')->nullable();
            $table->date('rank_checked_on')->nullable();
            $table->string('source', 40)->nullable();
            $table->foreignId('group_id')->nullable()->constrained('keyword_groups')->nullOnDelete();
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['product_id', 'marketplace_id', 'key_hash'], 'keywords_product_marketplace_hash_unique');
            $table->index('keyword_type', 'keywords_keyword_type_index');
        });

        Schema::create('keyword_rankings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('keyword_id')->constrained('keywords')->cascadeOnDelete();
            $table->date('checked_on');
            $table->unsignedInteger('rank');
            $table->string('rank_type', 10)->default('ORGANIC');
            $table->string('source', 40)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['keyword_id', 'checked_on', 'rank_type'], 'keyword_rankings_keyword_date_type_unique');
        });

        // Add the intelligence permissions without overwriting customized roles.
        DB::table('permissions')->insertOrIgnore([
            ['name' => 'keywords.view', 'module' => 'keywords', 'description' => 'View keyword research', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'keywords.manage', 'module' => 'keywords', 'description' => 'Manage keyword research', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'pricing.view', 'module' => 'pricing', 'description' => 'View pricing intelligence', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'pricing.manage', 'module' => 'pricing', 'description' => 'Calculate and save pricing analysis', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        // Drop child tables before their parents. price_history is intentionally
        // not included here; it belongs to the pricing migration.
        Schema::dropIfExists('keyword_rankings');
        Schema::dropIfExists('keywords');
        Schema::dropIfExists('keyword_groups');
        Schema::dropIfExists('listing_score_rules');
        Schema::dropIfExists('listing_versions');
        Schema::dropIfExists('listings');

        // Do not delete permissions here: they may have been assigned or
        // customized after this migration ran.
    }
};
