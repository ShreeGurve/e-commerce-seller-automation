<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_write_recovery_closure_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('recovery_case_id');
            $table->unsignedBigInteger('requested_by');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->string('status', 20)->default('PENDING');
            $table->text('resolution_summary');
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('business_id', 'mwrcv_business_fk')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('recovery_case_id', 'mwrcv_case_fk')->references('id')->on('marketplace_write_recovery_cases')->cascadeOnDelete();
            $table->foreign('requested_by', 'mwrcv_requester_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('reviewed_by', 'mwrcv_reviewer_fk')->references('id')->on('users')->nullOnDelete();
            $table->index(['business_id', 'status'], 'mwrcv_business_status_idx');
            $table->index(['recovery_case_id', 'created_at'], 'mwrcv_case_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_write_recovery_closure_reviews');
    }
};
