<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('settlements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('marketplace_id')->constrained()->restrictOnDelete();
            $t->string('external_settlement_id', 60);
            $t->date('period_start')->nullable();
            $t->date('period_end')->nullable();
            $t->decimal('total_amount', 15, 2)->default(0);     // net cash = sum of all lines
            $t->unsignedInteger('item_count')->default(0);
            $t->foreignId('import_id')->nullable()->constrained('data_imports')->nullOnDelete();
            $t->timestamps();
            $t->unique(['marketplace_id', 'external_settlement_id']);   // same settlement can never exist twice
        });

        Schema::create('settlement_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('settlement_id')->constrained()->restrictOnDelete();
            $t->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $t->string('external_order_id', 60)->nullable()->index();
            $t->string('sku', 120)->nullable();
            $t->string('kind', 20)->index();   // SALE|SHIPPING_CREDIT|TAX|REFUND|*_FEE|FEE_TAX|REIMBURSEMENT|ADJUSTMENT|OTHER
            $t->string('description')->nullable();
            $t->decimal('amount', 15, 2);      // signed: + money in, - money out
            $t->date('posted_date')->nullable();
            $t->char('row_hash', 40);
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['settlement_id', 'row_hash']);          // re-importing a file can never double-count a line
            $t->index(['order_id', 'kind']);
        });

        Schema::table('orders', function (Blueprint $t) {
            $t->string('recon_status', 12)->default('PENDING')->index()->after('status');   // PENDING|MATCHED|MISMATCH|NOT_APPLICABLE
        });

        Schema::create('reconciliation_issues', function (Blueprint $t) {
            $t->id();
            $t->string('issue_type', 24)->index();
            $t->foreignId('order_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('settlement_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('external_order_id', 60)->nullable();
            $t->decimal('expected_amount', 15, 2)->nullable();
            $t->decimal('actual_amount', 15, 2)->nullable();
            $t->decimal('difference', 15, 2)->nullable();
            $t->string('status', 10)->default('OPEN')->index();   // OPEN | RESOLVED | IGNORED
            $t->string('message', 500);
            $t->string('fingerprint', 120)->unique();             // one issue per problem, no duplicates on re-run
            $t->string('note', 500)->nullable();
            $t->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('detected_at')->nullable();
            $t->timestamp('resolved_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_issues');
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('recon_status'));
        Schema::dropIfExists('settlement_items');
        Schema::dropIfExists('settlements');
    }
};
