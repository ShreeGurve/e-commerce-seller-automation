<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('smart_reorder_runs')) Schema::create('smart_reorder_runs', function (Blueprint $t) {
            $t->id();
            $t->string('run_number', 40)->unique();
            $t->unsignedInteger('products_considered')->default(0);
            $t->unsignedInteger('items_added')->default(0);
            $t->unsignedBigInteger('requisition_id')->nullable();
            $t->foreign('requisition_id', 'srr_req_fk')->references('id')->on('procurement_requisitions')->nullOnDelete();
            $t->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index('created_at', 'srr_created_idx');
        });
    }
    public function down(): void { Schema::dropIfExists('smart_reorder_runs'); }
};
