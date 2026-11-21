<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_write_evidence', function (Blueprint $table) {
            $table->string('retention_status', 30)->default('ACTIVE')->after('reference_code');
            $table->timestamp('retention_expires_at')->nullable()->after('retention_status');
            $table->timestamp('archived_at')->nullable()->after('retention_expires_at');
            $table->timestamp('deletion_requested_at')->nullable()->after('archived_at');
            $table->unsignedBigInteger('deletion_requested_by')->nullable()->after('deletion_requested_at');
            $table->timestamp('deletion_approved_at')->nullable()->after('deletion_requested_by');
            $table->unsignedBigInteger('deletion_approved_by')->nullable()->after('deletion_approved_at');
            $table->index(['business_id', 'retention_status'], 'mw_ev_retention_status_idx');
            $table->index(['business_id', 'retention_expires_at'], 'mw_ev_retention_expiry_idx');
        });

        Schema::create('marketplace_write_retention_policies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->unique();
            $table->unsignedSmallInteger('retention_days')->default(365);
            $table->unsignedSmallInteger('alert_before_days')->default(30);
            $table->boolean('auto_archive')->default(true);
            $table->boolean('deletion_requests_enabled')->default(false);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('marketplace_write_evidence_holds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('evidence_id');
            $table->string('reason', 1000);
            $table->string('status', 20)->default('ACTIVE');
            $table->unsignedBigInteger('placed_by');
            $table->timestamp('placed_at');
            $table->unsignedBigInteger('released_by')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->string('release_reason', 1000)->nullable();
            $table->timestamps();
            $table->index(['business_id', 'evidence_id', 'status'], 'mw_ev_hold_lookup_idx');
        });

        Schema::create('marketplace_write_retention_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('evidence_id')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('event_type', 40);
            $table->text('details')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'created_at'], 'mw_ev_ret_events_idx');
            $table->index(['business_id', 'evidence_id'], 'mw_ev_ret_event_evidence_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_write_retention_events');
        Schema::dropIfExists('marketplace_write_evidence_holds');
        Schema::dropIfExists('marketplace_write_retention_policies');

        Schema::table('marketplace_write_evidence', function (Blueprint $table) {
            $table->dropIndex('mw_ev_retention_status_idx');
            $table->dropIndex('mw_ev_retention_expiry_idx');
            $table->dropColumn([
                'retention_status', 'retention_expires_at', 'archived_at',
                'deletion_requested_at', 'deletion_requested_by',
                'deletion_approved_at', 'deletion_approved_by',
            ]);
        });
    }
};
