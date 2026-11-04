<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('marketplace_id')->constrained()->restrictOnDelete();
            $table->decimal('current_price', 15, 2)->nullable();
            $table->decimal('proposed_price', 15, 2);
            $table->decimal('minimum_price', 15, 2)->nullable();
            $table->decimal('expected_profit', 15, 2)->nullable();
            $table->decimal('expected_margin', 8, 2)->nullable();
            $table->string('reason', 255)->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status'], 'pr_business_status_idx');
            $table->index(['product_id', 'marketplace_id'], 'pr_product_channel_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_recommendations');
    }
};
