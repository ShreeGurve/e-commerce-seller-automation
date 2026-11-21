<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_automation_execution_policies', function (Blueprint $table) {
            $table->id();
            $table->string('recommendation_type', 40)->unique('maep_type_uq');
            $table->boolean('dry_run_enabled')->default(true);
            $table->boolean('live_enabled')->default(false);
            $table->boolean('requires_approval')->default(true);
            $table->text('policy_note')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('marketplace_automation_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('marketplace_automation_tasks', 'id', 'mae_task_fk')->restrictOnDelete();
            $table->string('operation', 60);
            $table->string('mode', 12)->default('DRY_RUN');
            $table->string('status', 16)->default('STARTED');
            $table->string('idempotency_key', 100)->unique('mae_idempotency_uq');
            $table->json('request_payload')->nullable();
            $table->json('result_payload')->nullable();
            $table->json('validation_errors')->nullable();
            $table->text('message')->nullable();
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();
            $table->index(['task_id', 'created_at'], 'mae_task_created_idx');
            $table->index(['status', 'created_at'], 'mae_status_created_idx');
        });

        $now = now();
        foreach (['RESTOCK', 'STOCK_RISK', 'SALES_DECLINE', 'HIGH_RETURN_RATE', 'LOW_PROFIT_MARGIN'] as $type) {
            DB::table('marketplace_automation_execution_policies')->insert([
                'recommendation_type' => $type,
                'dry_run_enabled' => true,
                'live_enabled' => false,
                'requires_approval' => true,
                'policy_note' => 'Dry-run preview only. Live marketplace execution is not available in this release.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_automation_executions');
        Schema::dropIfExists('marketplace_automation_execution_policies');
    }
};
