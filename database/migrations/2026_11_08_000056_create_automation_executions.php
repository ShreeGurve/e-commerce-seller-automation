<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('automation_executions')) {
            Schema::create('automation_executions', function (Blueprint $table) {
                $table->id();
                $table->string('scope', 40)->default('all_active_rules');
                $table->string('status', 20)->default('running');
                $table->dateTime('started_at');
                $table->dateTime('finished_at')->nullable();
                $table->unsignedInteger('duration_ms')->nullable();
                $table->unsignedInteger('rules_count')->default(0);
                $table->unsignedInteger('matched_count')->default(0);
                $table->unsignedInteger('created_count')->default(0);
                $table->text('error_message')->nullable();
                $table->unsignedBigInteger('retry_of_id')->nullable();
                $table->unsignedBigInteger('triggered_by')->nullable();
                $table->timestamps();
                $table->index(['status', 'started_at'], 'ae_status_started_idx');
                $table->index(['retry_of_id'], 'ae_retry_of_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_executions');
    }
};
