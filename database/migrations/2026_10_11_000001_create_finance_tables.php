<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Configurable marketplace fee rules. No fee is ever hard-coded in PHP.
        Schema::create('fee_rules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('marketplace_id')->constrained()->restrictOnDelete();
            $t->foreignId('category_id')->nullable()->constrained()->nullOnDelete();   // null = all categories
            $t->string('fee_type', 20);                    // REFERRAL|CLOSING|SHIPPING|COLLECTION|PAYMENT|OTHER
            $t->string('name')->nullable();
            $t->decimal('percentage', 7, 4)->default(0);   // % of the base price
            $t->decimal('fixed_amount', 15, 2)->default(0); // per UNIT
            $t->string('applies_on', 12)->default('PRICE'); // PRICE (incl GST) | PRICE_EX_TAX
            $t->unsignedInteger('weight_min_g')->nullable();
            $t->unsignedInteger('weight_max_g')->nullable();
            $t->date('effective_from');
            $t->date('effective_to')->nullable();
            $t->boolean('is_active')->default(true);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['marketplace_id', 'fee_type', 'effective_from']);
        });

        // Fee lines per order item. ESTIMATED from rules now; SETTLEMENT (actual) lines arrive in Phase 4b.
        Schema::create('order_item_fees', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $t->string('fee_type', 20);
            $t->decimal('amount', 15, 2);
            $t->decimal('tax_amount', 15, 2)->default(0);  // GST charged on the fee
            $t->string('source', 10)->default('ESTIMATED'); // ESTIMATED | SETTLEMENT
            $t->unsignedBigInteger('rule_id')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['order_item_id', 'source']);
        });

        // Derived profit per order line. Denormalised (date, product, marketplace) so reports stay fast.
        Schema::create('order_profits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_item_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->foreignId('marketplace_id')->constrained()->restrictOnDelete();
            $t->date('order_date');
            $t->unsignedInteger('qty');
            $t->decimal('revenue', 15, 2);       // item price ex-GST + shipping charged
            $t->decimal('cogs', 15, 2);          // COGS+packaging+other, minus units restocked from returns
            $t->decimal('fees', 15, 2);          // marketplace fees (+ GST on fees)
            $t->decimal('return_loss', 15, 2)->default(0);
            $t->decimal('recovery', 15, 2)->default(0);   // claims / reimbursements
            $t->decimal('ads_cost', 15, 2)->default(0);   // Phase 5
            $t->decimal('estimated_profit', 15, 2);
            $t->decimal('actual_profit', 15, 2)->nullable(); // set once settlement data exists (Phase 4b)
            $t->string('profit_status', 10)->default('ESTIMATED');
            $t->timestamp('calculated_at')->nullable();
            $t->index('order_date');
            $t->index(['product_id', 'order_date']);
            $t->index(['marketplace_id', 'order_date']);
            $t->index('order_id');
        });

        Schema::create('expense_categories', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->boolean('is_system')->default(false);
            $t->timestamps();
        });
        $now = now();
        foreach (['Packaging', 'Courier', 'Warehouse', 'Salary', 'Advertising', 'Software', 'Transportation', 'Electricity', 'Internet', 'Marketplace Fees', 'Product Damage', 'Returns', 'Other'] as $n) {
            DB::table('expense_categories')->insert(['name' => $n, 'is_system' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        Schema::create('expenses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $t->date('expense_date');
            $t->decimal('amount', 15, 2);
            $t->foreignId('marketplace_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $t->string('invoice_number', 60)->nullable();
            $t->text('notes')->nullable();
            $t->string('attachment_path')->nullable();     // private disk
            $t->string('attachment_name')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
            $t->index('expense_date');
        });
    }

    public function down(): void
    {
        foreach (['expenses', 'expense_categories', 'order_profits', 'order_item_fees', 'fee_rules'] as $t) Schema::dropIfExists($t);
    }
};
