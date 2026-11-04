<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('supplier_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // NOTE, FOLLOW_UP, DOCUMENT, ISSUE
            $table->string('subject', 180);
            $table->text('details')->nullable();
            $table->string('reference_number', 100)->nullable();
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('OPEN'); // OPEN, IN_PROGRESS, DONE, CANCELLED
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['supplier_id', 'type'], 'si_supplier_type_idx');
            $table->index(['status', 'due_date'], 'si_status_due_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_interactions');
    }
};
