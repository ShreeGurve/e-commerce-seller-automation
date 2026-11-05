<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  if (!Schema::hasTable('business_automation_rules')) Schema::create('business_automation_rules', function(Blueprint $t) {
   $t->id(); $t->string('rule_key',60)->unique(); $t->string('name',150); $t->text('description')->nullable();
   $t->decimal('threshold_value',14,3)->default(0); $t->boolean('is_enabled')->default(true);
   $t->dateTime('last_evaluated_at')->nullable(); $t->timestamps();
  });
  if (!Schema::hasTable('business_automation_events')) Schema::create('business_automation_events', function(Blueprint $t) {
   $t->id(); $t->foreignId('rule_id')->nullable()->constrained('business_automation_rules')->nullOnDelete();
   $t->string('event_key',100); $t->string('severity',20)->default('WARNING'); $t->string('title',180);
   $t->text('message'); $t->string('source_type',60)->nullable(); $t->unsignedBigInteger('source_id')->nullable();
   $t->string('event_fingerprint',120)->unique(); $t->string('status',20)->default('OPEN');
   $t->dateTime('resolved_at')->nullable(); $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
   $t->timestamps(); $t->index(['status','severity'],'bae_status_severity_idx');
  });
 }
 public function down(): void { Schema::dropIfExists('business_automation_events'); Schema::dropIfExists('business_automation_rules'); }
};
