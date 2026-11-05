<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('procurement_requisitions')) Schema::create('procurement_requisitions', function(Blueprint $t) {
            $t->id(); $t->string('request_number',40)->unique(); $t->string('title',180);
            $t->text('purpose')->nullable(); $t->date('required_by')->nullable();
            $t->string('priority',20)->default('NORMAL'); $t->string('status',24)->default('DRAFT');
            $t->unsignedBigInteger('requested_by')->nullable(); $t->unsignedBigInteger('approved_by')->nullable();
            $t->dateTime('approved_at')->nullable(); $t->text('approval_notes')->nullable(); $t->timestamps();
            $t->foreign('requested_by','pr_req_user_fk')->references('id')->on('users')->nullOnDelete();
            $t->foreign('approved_by','pr_app_user_fk')->references('id')->on('users')->nullOnDelete();
            $t->index(['status','required_by'],'pr_status_due_idx');
        });
        if (!Schema::hasTable('procurement_requisition_items')) Schema::create('procurement_requisition_items', function(Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('requisition_id'); $t->string('item_name',180);
            $t->string('sku',100)->nullable(); $t->decimal('quantity',14,3); $t->string('unit',30)->default('pcs');
            $t->decimal('estimated_unit_cost',14,2)->default(0); $t->text('specifications')->nullable(); $t->timestamps();
            $t->foreign('requisition_id','pri_req_fk')->references('id')->on('procurement_requisitions')->cascadeOnDelete();
        });
    }
    public function down(): void { Schema::dropIfExists('procurement_requisition_items'); Schema::dropIfExists('procurement_requisitions'); }
};
