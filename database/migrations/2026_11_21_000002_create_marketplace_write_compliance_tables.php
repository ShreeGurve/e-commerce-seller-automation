<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_write_compliance_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('started_by')->nullable();
            $table->unsignedInteger('finding_count')->default(0);
            $table->unsignedInteger('critical_count')->default(0);
            $table->unsignedInteger('warning_count')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'created_at'], 'mwcc_run_business_time_idx');
        });

        Schema::create('marketplace_write_compliance_findings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('run_id');
            $table->string('rule_key', 64);
            $table->string('severity', 16)->default('WARNING');
            $table->string('subject_type', 40)->default('GENERAL');
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('title', 180);
            $table->text('details')->nullable();
            $table->string('status', 20)->default('OPEN');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['business_id', 'run_id', 'severity'], 'mwcc_find_business_run_idx');
            $table->index(['business_id', 'status'], 'mwcc_find_business_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_write_compliance_findings');
        Schema::dropIfExists('marketplace_write_compliance_runs');
    }
};
