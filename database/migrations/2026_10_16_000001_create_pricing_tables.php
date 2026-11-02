<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // A record of the selling prices YOU set. Nothing here changes a price on a marketplace.
        Schema::create('price_history', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->foreignId('marketplace_id')->nullable()->constrained()->restrictOnDelete();   // null = master price
            $t->decimal('price', 15, 2);                   // selling price incl. GST
            $t->decimal('previous_price', 15, 2)->nullable();
            $t->string('reason')->nullable();
            $t->string('source', 10)->default('MANUAL');   // MANUAL | MASTER
            $t->date('effective_on');
            $t->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['product_id', 'marketplace_id', 'effective_on']);
        });

        // Competitor prices are typed in or imported from your own research / approved sources. No scraping.
        Schema::create('competitor_prices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->foreignId('marketplace_id')->constrained()->restrictOnDelete();
            $t->string('competitor_name', 120);
            $t->decimal('competitor_price', 15, 2);
            $t->decimal('our_price', 15, 2)->nullable();   // our price on that day, for the difference
            $t->date('captured_on');
            $t->string('source', 30)->nullable();
            $t->string('url', 500)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['product_id', 'marketplace_id', 'competitor_name', 'captured_on'], 'comp_price_unique');
            $t->index(['product_id', 'marketplace_id', 'captured_on']);
        });
    }

    public function down(): void { Schema::dropIfExists('competitor_prices'); Schema::dropIfExists('price_history'); }
};
