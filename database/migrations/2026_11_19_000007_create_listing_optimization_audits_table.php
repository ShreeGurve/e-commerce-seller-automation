<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('listing_optimization_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained('listings', 'id', 'loa_listing_fk')->cascadeOnDelete();
            $table->foreignId('listing_version_id')->constrained('listing_versions', 'id', 'loa_version_fk')->cascadeOnDelete();
            $table->decimal('score', 5, 1)->default(0);
            $table->string('status', 16)->default('REVIEW');
            $table->unsignedSmallInteger('issue_count')->default(0);
            $table->unsignedSmallInteger('blocker_count')->default(0);
            $table->unsignedSmallInteger('warning_count')->default(0);
            $table->json('results')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', 'id', 'loa_user_fk')->nullOnDelete();
            $table->timestamps();
            $table->index(['listing_id', 'created_at'], 'loa_listing_created_idx');
            $table->index(['status', 'created_at'], 'loa_status_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_optimization_audits');
    }
};
