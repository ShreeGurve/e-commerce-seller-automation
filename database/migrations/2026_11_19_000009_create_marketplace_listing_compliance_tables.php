<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketplace_listing_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained('listings', 'id', 'mli_listing_fk')->cascadeOnDelete();
            $table->foreignId('listing_version_id')->nullable()->constrained('listing_versions', 'id', 'mli_version_fk')->nullOnDelete();
            $table->string('external_issue_id', 120)->nullable();
            $table->string('issue_code', 100);
            $table->string('issue_type', 32)->default('COMPLIANCE');
            $table->string('severity', 16)->default('WARNING');
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('source', 16)->default('MANUAL');
            $table->string('status', 20)->default('OPEN');
            $table->boolean('is_suppression')->default(false);
            $table->timestamp('detected_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->json('evidence')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', 'id', 'mli_created_user_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users', 'id', 'mli_updated_user_fk')->nullOnDelete();
            $table->timestamps();
            $table->index(['listing_id', 'status'], 'mli_listing_status_idx');
            $table->index(['status', 'severity'], 'mli_status_severity_idx');
            $table->index(['is_suppression', 'status'], 'mli_suppress_status_idx');
            $table->index(['external_issue_id'], 'mli_external_issue_idx');
        });

        Schema::create('marketplace_listing_issue_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained('marketplace_listing_issues', 'id', 'mlie_issue_fk')->cascadeOnDelete();
            $table->string('event_type', 32);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->text('note')->nullable();
            $table->json('evidence')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users', 'id', 'mlie_user_fk')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['issue_id', 'created_at'], 'mlie_issue_created_idx');
        });

        Schema::create('marketplace_listing_compliance_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained('listings', 'id', 'mlcc_listing_fk')->cascadeOnDelete();
            $table->string('check_type', 24)->default('MANUAL_RECHECK');
            $table->string('result', 20)->default('ISSUES_OPEN');
            $table->unsignedInteger('open_issues')->default(0);
            $table->unsignedInteger('suppression_issues')->default(0);
            $table->json('summary')->nullable();
            $table->foreignId('checked_by')->nullable()->constrained('users', 'id', 'mlcc_user_fk')->nullOnDelete();
            $table->timestamps();
            $table->index(['listing_id', 'created_at'], 'mlcc_listing_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_listing_compliance_checks');
        Schema::dropIfExists('marketplace_listing_issue_events');
        Schema::dropIfExists('marketplace_listing_issues');
    }
};
