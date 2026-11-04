<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('purchases', 'expected_delivery_date')) {
            Schema::table('purchases', function (Blueprint $table) {
                $table->date('expected_delivery_date')->nullable()->after('purchase_date');
                $table->index('expected_delivery_date', 'purchases_expected_delivery_idx');
            });
        }

        if (! Schema::hasTable('supplier_reviews')) {
            Schema::create('supplier_reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
                $table->unsignedTinyInteger('delivery_rating');
                $table->unsignedTinyInteger('quality_rating');
                $table->unsignedTinyInteger('pricing_rating');
                $table->text('notes')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at');
                $table->timestamps();
                $table->index(['supplier_id', 'reviewed_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_reviews');
        if (Schema::hasColumn('purchases', 'expected_delivery_date')) {
            Schema::table('purchases', function (Blueprint $table) {
                $table->dropIndex('purchases_expected_delivery_idx');
                $table->dropColumn('expected_delivery_date');
            });
        }
    }
};
