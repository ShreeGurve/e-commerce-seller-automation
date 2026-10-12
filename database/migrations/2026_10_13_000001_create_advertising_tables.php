<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('advertising_campaigns', function (Blueprint $t) {
            $t->id();
            $t->foreignId('marketplace_id')->constrained()->restrictOnDelete();
            $t->string('name', 200);
            $t->decimal('daily_budget', 15, 2)->nullable();
            $t->timestamps();
            $t->unique(['marketplace_id', 'name']);
        });
        Schema::create('advertising_groups', function (Blueprint $t) {
            $t->id();
            $t->foreignId('campaign_id')->constrained('advertising_campaigns')->cascadeOnDelete();
            $t->string('name', 200);                       // "(no ad group)" when the report has none
            $t->timestamps();
            $t->unique(['campaign_id', 'name']);
        });
        Schema::create('advertising_keywords', function (Blueprint $t) {
            $t->id();
            $t->foreignId('campaign_id')->constrained('advertising_campaigns')->cascadeOnDelete();
            $t->foreignId('group_id')->constrained('advertising_groups')->cascadeOnDelete();
            $t->string('keyword', 255);
            $t->string('match_type', 12)->default('');      // BROAD|PHRASE|EXACT|AUTO|'' (targeting / product target)
            $t->char('key_hash', 40);                       // sha1(lower(keyword)): short unique key
            $t->timestamps();
            $t->unique(['group_id', 'match_type', 'key_hash']);
        });
        Schema::create('advertising_search_terms', function (Blueprint $t) {
            $t->id();
            $t->foreignId('campaign_id')->constrained('advertising_campaigns')->cascadeOnDelete();
            $t->foreignId('group_id')->constrained('advertising_groups')->cascadeOnDelete();
            $t->foreignId('keyword_id')->nullable()->constrained('advertising_keywords')->nullOnDelete();  // the keyword that triggered it
            $t->string('search_term', 255);
            $t->char('target_hash', 40);                    // sha1(lower(keyword|match))
            $t->char('key_hash', 40);                       // sha1(lower(search term))
            $t->timestamps();
            $t->unique(['group_id', 'target_hash', 'key_hash']);
        });

        // One fact table. `level` says how detailed the imported report was; totals never mix levels (no double counting).
        Schema::create('advertising_daily', function (Blueprint $t) {
            $t->id();
            $t->foreignId('marketplace_id')->constrained()->restrictOnDelete();
            $t->date('date');
            $t->string('level', 8);                         // CAMPAIGN | KEYWORD | TERM | PRODUCT
            $t->foreignId('campaign_id')->constrained('advertising_campaigns')->cascadeOnDelete();
            $t->foreignId('group_id')->nullable()->constrained('advertising_groups')->nullOnDelete();
            $t->foreignId('keyword_id')->nullable()->constrained('advertising_keywords')->nullOnDelete();
            $t->foreignId('search_term_id')->nullable()->constrained('advertising_search_terms')->nullOnDelete();
            $t->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedInteger('impressions')->default(0);
            $t->unsignedInteger('clicks')->default(0);
            $t->decimal('spend', 15, 2)->default(0);
            $t->decimal('sales', 15, 2)->default(0);
            $t->unsignedInteger('orders')->default(0);
            $t->unsignedInteger('units')->default(0);
            $t->char('row_key', 40)->unique();              // same day + campaign + target = same row: re-import updates, never duplicates
            $t->foreignId('import_id')->nullable()->constrained('data_imports')->nullOnDelete();
            $t->timestamps();
            $t->index(['marketplace_id', 'date', 'level']);
            $t->index(['campaign_id', 'date']);
            $t->index(['product_id', 'date']);
        });
    }

    public function down(): void
    {
        foreach (['advertising_daily', 'advertising_search_terms', 'advertising_keywords', 'advertising_groups', 'advertising_campaigns'] as $t) Schema::dropIfExists($t);
    }
};
