<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_write_governance_policies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->unique('mwgp_business_unique');
            $table->boolean('writes_enabled')->default(false);
            $table->boolean('inventory_auto_sync')->default(false);
            $table->boolean('require_second_person')->default(true);
            $table->boolean('require_evidence_reference')->default(true);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('marketplace_write_governance_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('requested_by');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->string('status', 20)->default('PENDING');
            $table->json('proposed_changes');
            $table->text('request_reason');
            $table->text('review_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'status', 'created_at'], 'mwgr_business_status_time_idx');
        });

        Schema::create('marketplace_write_governance_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->unsignedBigInteger('request_id')->nullable();
            $table->string('event_type', 40);
            $table->text('details')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'created_at'], 'mwge_business_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_write_governance_events');
        Schema::dropIfExists('marketplace_write_governance_requests');
        Schema::dropIfExists('marketplace_write_governance_policies');
    }
};
