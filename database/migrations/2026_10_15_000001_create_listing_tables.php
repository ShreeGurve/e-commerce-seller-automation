<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->foreignId('marketplace_id')->constrained()->restrictOnDelete();
            $t->string('status', 12)->default('DRAFT');                  // DRAFT | LIVE | SUPPRESSED | INACTIVE
            $t->unsignedBigInteger('current_version_id')->nullable();    // newest version (your working copy)
            $t->unsignedBigInteger('published_version_id')->nullable();  // the version you applied on the marketplace
            $t->string('category_path')->nullable();
            $t->string('brand', 120)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['product_id', 'marketplace_id']);
        });

        // Versions are never edited: every save is a new row, so any version can be compared or restored.
        Schema::create('listing_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('version_no');
            $t->string('title', 500);
            $t->json('bullets')->nullable();
            $t->text('description')->nullable();
            $t->string('backend_keywords', 500)->nullable();
            $t->text('special_features')->nullable();
            $t->text('aplus_content')->nullable();
            $t->unsignedSmallInteger('image_count')->default(0);
            $t->boolean('has_video')->default(false);
            $t->decimal('score', 5, 2)->default(0);
            $t->json('score_breakdown')->nullable();
            $t->string('change_note')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['listing_id', 'version_no']);
        });

        Schema::create('listing_score_rules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('marketplace_id')->nullable()->constrained()->cascadeOnDelete();   // null = all channels; a channel row overrides it
            $t->string('criterion', 20);
            $t->decimal('max_points', 5, 2);
            $t->json('params')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['marketplace_id', 'criterion']);
        });
        $now = now();
        foreach ([['title', 20, ['min_len' => 80, 'max_len' => 200, 'front_chars' => 80]], ['bullets', 20, ['count' => 5, 'min_len' => 100, 'max_len' => 500]],
                  ['description', 10, ['min_len' => 1000, 'max_len' => 2000]], ['keywords', 15, []], ['images', 15, ['target' => 7]], ['aplus', 10, ['min_len' => 200]],
                  ['features', 5, ['target' => 3]], ['backend', 5, ['min_bytes' => 150, 'max_bytes' => 250]]] as [$c, $max, $params]) {
            DB::table('listing_score_rules')->insert(['marketplace_id' => null, 'criterion' => $c, 'max_points' => $max, 'params' => json_encode($params), 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        Schema::create('keyword_groups', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->string('description')->nullable();
            $t->timestamps();
        });
        Schema::create('keywords', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->foreignId('marketplace_id')->constrained()->restrictOnDelete();
            $t->string('keyword', 255);
            $t->char('key_hash', 40);                                    // sha1(lower(keyword)): one row per keyword per product per channel
            $t->string('keyword_type', 12)->default('SECONDARY');       // PRIMARY|SECONDARY|LONG_TAIL|BACKEND|AEO|SEO|GEO
            $t->unsignedInteger('search_volume')->nullable();
            $t->string('competition', 8)->nullable();                    // LOW | MEDIUM | HIGH
            $t->unsignedTinyInteger('relevance')->nullable();            // 0-100
            $t->unsignedInteger('current_rank')->nullable();             // latest known position
            $t->date('rank_checked_on')->nullable();
            $t->string('source', 40)->nullable();
            $t->foreignId('group_id')->nullable()->constrained('keyword_groups')->nullOnDelete();
            $t->string('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['product_id', 'marketplace_id', 'key_hash']);
            $t->index('keyword_type');
        });
        Schema::create('keyword_rankings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('keyword_id')->constrained()->cascadeOnDelete();
            $t->date('checked_on');
            $t->unsignedInteger('rank');
            $t->string('rank_type', 10)->default('ORGANIC');             // ORGANIC | SPONSORED
            $t->string('source', 40)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['keyword_id', 'checked_on', 'rank_type']);
        });
    }

    public function down(): void
    {
        foreach (['keyword_rankings', 'keywords', 'keyword_groups', 'listing_score_rules', 'listing_versions', 'listings'] as $t) Schema::dropIfExists($t);
    }
};
