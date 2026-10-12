<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $t->string('sku', 100)->unique();
            $t->string('parent_sku', 100)->nullable()->index();
            $t->string('name');
            $t->string('subcategory', 120)->nullable();
            $t->string('product_type', 80)->nullable();
            $t->string('hsn', 20)->nullable();
            $t->decimal('gst_rate', 5, 2)->nullable();
            $t->decimal('mrp', 15, 2)->nullable();
            $t->decimal('default_selling_price', 15, 2)->nullable();
            $t->decimal('min_selling_price', 15, 2)->nullable();
            $t->decimal('target_selling_price', 15, 2)->nullable();
            $t->decimal('weight_g', 10, 2)->nullable();
            $t->decimal('length_cm', 10, 2)->nullable();
            $t->decimal('width_cm', 10, 2)->nullable();
            $t->decimal('height_cm', 10, 2)->nullable();
            $t->string('status', 20)->default('active')->index();
            $t->string('image_path')->nullable();
            $t->text('description')->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
            // NOTE: COGS/packaging are NOT columns here. They live in product_cost_history.
        });

        Schema::create('product_variants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->string('sku', 100)->unique();
            $t->string('name')->nullable();
            $t->json('attributes')->nullable();
            $t->string('barcode', 60)->nullable();
            $t->string('status', 20)->default('active');
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('product_marketplace', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $t->foreignId('marketplace_id')->constrained()->restrictOnDelete();
            $t->string('marketplace_sku', 120);
            $t->string('external_product_id', 60)->nullable(); // ASIN / FSN
            $t->string('fulfillment_sku', 60)->nullable();     // FNSKU (Amazon)
            $t->string('listing_status', 30)->default('active');
            $t->timestamps();
            $t->unique(['marketplace_id', 'marketplace_sku']);
            $t->index('external_product_id');
        });

        Schema::create('product_cost_history', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->string('cost_type', 20);                       // COGS|PACKAGING|LABEL|TRANSPORT|OTHER
            $t->decimal('amount', 15, 2);
            $t->date('effective_from');
            $t->date('effective_to')->nullable();              // NULL = currently active
            $t->string('note')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['product_id', 'cost_type', 'effective_from'], 'pch_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_cost_history');
        Schema::dropIfExists('product_marketplace');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
    }
};
