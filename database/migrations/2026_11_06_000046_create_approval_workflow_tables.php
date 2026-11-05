<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('approval_workflow_policies')) Schema::create('approval_workflow_policies', function(Blueprint $t) {
            $t->id(); $t->string('request_type',60)->unique();
            $t->decimal('min_amount',15,2)->nullable(); $t->decimal('max_amount',15,2)->nullable();
            $t->unsignedTinyInteger('approval_levels')->default(1);
            $t->string('reviewer_permission',80)->default('settings.manage');
            $t->boolean('is_enabled')->default(true);
            $t->timestamps();
        });
        if (!Schema::hasTable('approval_workflow_steps')) Schema::create('approval_workflow_steps', function(Blueprint $t) {
            $t->id(); $t->foreignId('approval_request_id')->constrained('approval_requests')->cascadeOnDelete();
            $t->unsignedTinyInteger('step_number'); $t->string('status',20)->default('pending');
            $t->string('required_permission',80)->default('settings.manage');
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->dateTime('reviewed_at')->nullable(); $t->text('decision_note')->nullable();
            $t->timestamps();
            $t->unique(['approval_request_id','step_number'],'aws_request_step_unique');
            $t->index(['approval_request_id','status'],'aws_request_status_idx');
        });
    }
    public function down(): void { Schema::dropIfExists('approval_workflow_steps'); Schema::dropIfExists('approval_workflow_policies'); }
};
