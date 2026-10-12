<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('data_imports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('marketplace_id')->constrained()->restrictOnDelete();
            $t->string('data_type', 12);                       // ORDERS | RETURNS | MAPPINGS
            $t->string('file_name');
            $t->string('stored_path');                         // private disk: storage/app/private/imports/...
            $t->char('file_hash', 64);
            $t->unsignedBigInteger('file_size')->default(0);
            $t->string('status', 12)->default('UPLOADED')->index(); // UPLOADED|MAPPED|RUNNING|IMPORTED|FAILED
            $t->json('options')->nullable();                   // price_mode, date_format, deduct_stock
            $t->json('mapping')->nullable();                   // internal field => file column
            $t->unsignedInteger('total_rows')->default(0);
            $t->unsignedInteger('ok_rows')->default(0);
            $t->unsignedInteger('failed_rows')->default(0);
            $t->unsignedInteger('duplicate_rows')->default(0);
            $t->string('error_message', 500)->nullable();
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('imported_at')->nullable();
            $t->timestamps();
            $t->index(['marketplace_id', 'data_type', 'file_hash']);
        });

        Schema::create('import_rows', function (Blueprint $t) {
            $t->id();
            $t->foreignId('import_id')->constrained('data_imports')->cascadeOnDelete();
            $t->unsignedInteger('row_number');
            $t->string('status', 10);                          // OK | ERROR | DUPLICATE
            $t->string('message', 500)->nullable();
            $t->json('raw')->nullable();                       // original values kept for non-OK rows only
            $t->string('entity_type', 30)->nullable();
            $t->unsignedBigInteger('entity_id')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['import_id', 'status']);
        });

        Schema::create('import_column_mappings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('marketplace_id')->constrained()->cascadeOnDelete();
            $t->string('data_type', 12);
            $t->json('mapping');
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['marketplace_id', 'data_type']);       // last used mapping is remembered
        });

        Schema::table('orders', function (Blueprint $t) {
            $t->foreign('import_id')->references('id')->on('data_imports')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $t) => $t->dropForeign(['import_id']));
        Schema::dropIfExists('import_column_mappings');
        Schema::dropIfExists('import_rows');
        Schema::dropIfExists('data_imports');
    }
};
