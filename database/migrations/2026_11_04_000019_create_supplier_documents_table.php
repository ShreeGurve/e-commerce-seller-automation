<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 60);
            $table->string('title', 180);
            $table->string('original_filename', 255);
            $table->string('disk', 40)->default('local');
            $table->string('stored_path', 500);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('file_size');
            $table->string('reference_number', 120)->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('status', 20)->default('PENDING');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['supplier_id', 'status'], 'sd_supplier_status_idx');
            $table->index(['status', 'expires_at'], 'sd_status_expiry_idx');
            $table->index(['document_type', 'expires_at'], 'sd_type_expiry_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_documents');
    }
};
