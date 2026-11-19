<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('dynamic_pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->nullable();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('marketplace_id');
            $table->string('strategy', 32)->default('TARGET_MARGIN');
            $table->decimal('floor_price', 15, 2)->nullable();
            $table->decimal('ceiling_price', 15, 2)->nullable();
            $table->decimal('minimum_margin_percent', 7, 2)->default(5);
            $table->decimal('competitor_adjustment_percent', 7, 2)->default(1);
            $table->boolean('enabled')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->foreign('business_id', 'dpr_business_fk')->references('id')->on('businesses')->nullOnDelete();
            $table->foreign('product_id', 'dpr_product_fk')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('marketplace_id', 'dpr_market_fk')->references('id')->on('marketplaces')->cascadeOnDelete();
            $table->foreign('created_by', 'dpr_creator_fk')->references('id')->on('users')->nullOnDelete();
            $table->unique(['product_id', 'marketplace_id'], 'dpr_product_market_unique');
            $table->index(['business_id', 'enabled'], 'dpr_business_enabled_idx');
        });

        Schema::create('dynamic_pricing_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->nullable();
            $table->unsignedBigInteger('marketplace_id')->nullable();
            $table->string('status', 20)->default('RUNNING');
            $table->unsignedInteger('rules_checked')->default(0);
            $table->unsignedInteger('recommendations_created')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->text('summary')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->foreign('business_id', 'dprun_business_fk')->references('id')->on('businesses')->nullOnDelete();
            $table->foreign('marketplace_id', 'dprun_market_fk')->references('id')->on('marketplaces')->nullOnDelete();
            $table->index(['business_id', 'created_at'], 'dprun_business_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dynamic_pricing_runs');
        Schema::dropIfExists('dynamic_pricing_rules');
    }
};
