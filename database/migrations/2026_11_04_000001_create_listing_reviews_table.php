<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('listing_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->foreignId('listing_version_id')->constrained('listing_versions')->cascadeOnDelete();
            $table->string('status', 24)->default('PENDING');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['listing_id', 'listing_version_id', 'status'], 'listing_reviews_lookup_index');
            $table->index(['status', 'requested_at'], 'listing_reviews_queue_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_reviews');
    }
};
