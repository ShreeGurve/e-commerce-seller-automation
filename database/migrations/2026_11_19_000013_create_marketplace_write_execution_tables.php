<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketplace_write_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_connection_id')->constrained('marketplace_connections', 'id', 'mwp_conn_fk')->cascadeOnDelete();
            $table->string('operation', 32);
            $table->boolean('is_enabled')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users', 'id', 'mwp_user_fk')->nullOnDelete();
            $table->timestamp('enabled_at')->nullable();
            $table->timestamps();
            $table->unique(['marketplace_connection_id', 'operation'], 'mwp_conn_operation_uq');
        });

        Schema::create('marketplace_write_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->foreign('business_id', 'mwa_business_fk')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreignId('marketplace_connection_id')->constrained('marketplace_connections', 'id', 'mwa_conn_fk')->restrictOnDelete();
            $table->foreignId('task_id')->constrained('marketplace_automation_tasks', 'id', 'mwa_task_fk')->restrictOnDelete();
            $table->string('marketplace_code', 20);
            $table->string('operation', 32);
            $table->string('status', 24)->default('PREPARED');
            $table->string('marketplace_sku', 120);
            $table->string('idempotency_key', 100)->unique('mwa_idempotency_uq');
            $table->char('payload_hash', 64);
            $table->json('request_payload');
            $table->json('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('prepared_by')->nullable()->constrained('users', 'id', 'mwa_prepared_user_fk')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users', 'id', 'mwa_approved_user_fk')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'status', 'created_at'], 'mwa_business_status_idx');
            $table->index(['marketplace_connection_id', 'operation', 'created_at'], 'mwa_conn_operation_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_write_attempts');
        Schema::dropIfExists('marketplace_write_policies');
    }
};
