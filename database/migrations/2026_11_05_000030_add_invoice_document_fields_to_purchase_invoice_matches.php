<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_invoice_matches', function (Blueprint $table) {
            $table->string('supplier_gstin', 15)->nullable();
            $table->string('buyer_gstin', 15)->nullable();
            $table->string('place_of_supply', 100)->nullable();
            $table->string('document_path')->nullable();
            $table->string('document_original_name')->nullable();
            $table->string('document_mime', 100)->nullable();
            $table->unsignedBigInteger('document_size')->nullable();
            $table->text('extracted_text')->nullable();
            $table->string('document_processing_status', 24)->default('NOT_UPLOADED');
            $table->string('gst_validation_status', 24)->default('NOT_CHECKED');
            $table->text('gst_validation_notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_invoice_matches', function (Blueprint $table) {
            $table->dropColumn([
                'supplier_gstin', 'buyer_gstin', 'place_of_supply', 'document_path',
                'document_original_name', 'document_mime', 'document_size', 'extracted_text',
                'document_processing_status', 'gst_validation_status', 'gst_validation_notes',
            ]);
        });
    }
};
