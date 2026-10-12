<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('claims', function (Blueprint $t) {
            $t->id();
            $t->string('claim_number', 30)->unique();                 // CL-2026-000001
            $t->foreignId('marketplace_id')->constrained()->restrictOnDelete();
            $t->string('external_claim_id', 60)->nullable();          // case / claim id on the marketplace portal
            $t->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('return_id')->nullable()->constrained('returns')->nullOnDelete();
            $t->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $t->string('claim_type', 24);
            $t->string('status', 18)->default('DRAFT')->index();
            $t->decimal('claim_amount', 15, 2);
            $t->decimal('approved_amount', 15, 2)->default(0);        // counts as recovery in profit
            $t->decimal('rejected_amount', 15, 2)->default(0);
            $t->date('submitted_at')->nullable();
            $t->date('deadline')->nullable()->index();                // last day to file / appeal
            $t->date('decision_date')->nullable();
            $t->unsignedTinyInteger('appeal_count')->default(0);
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['marketplace_id', 'external_claim_id']);      // a portal claim id can only be tracked once
            $t->index('order_id');
        });

        Schema::create('claim_documents', function (Blueprint $t) {   // evidence: photos, videos, PDFs. Files live on the private disk.
            $t->id();
            $t->foreignId('claim_id')->constrained()->cascadeOnDelete();
            $t->string('path');
            $t->string('original_name');
            $t->string('mime', 60);
            $t->unsignedInteger('size');
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('claim_events', function (Blueprint $t) {      // timeline: who did what, when
            $t->id();
            $t->foreignId('claim_id')->constrained()->cascadeOnDelete();
            $t->string('event', 16);                                  // CREATED | STATUS | NOTE | DOCUMENT
            $t->string('from_status', 18)->nullable();
            $t->string('to_status', 18)->nullable();
            $t->decimal('amount', 15, 2)->nullable();
            $t->string('note', 500)->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_events');
        Schema::dropIfExists('claim_documents');
        Schema::dropIfExists('claims');
    }
};
