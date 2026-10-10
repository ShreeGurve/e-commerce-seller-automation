<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('contact_person', 120)->nullable();
            $t->string('phone', 30)->nullable();
            $t->string('email')->nullable();
            $t->string('gstin', 20)->nullable();
            $t->string('address', 500)->nullable();
            $t->string('city', 80)->nullable();
            $t->string('state', 80)->nullable();
            $t->string('payment_terms', 120)->nullable();
            $t->text('notes')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->softDeletes();
            $t->index('name');
        });

        Schema::create('purchases', function (Blueprint $t) {
            $t->id();
            $t->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $t->string('po_number', 30)->unique();
            $t->date('purchase_date');
            $t->string('invoice_number', 60)->nullable();
            $t->date('invoice_date')->nullable();
            $t->string('status', 15)->default('DRAFT');          // DRAFT|ORDERED|RECEIVED|CANCELLED
            $t->string('payment_status', 10)->default('UNPAID'); // UNPAID|PARTIAL|PAID
            $t->decimal('subtotal', 15, 2)->default(0);
            $t->decimal('gst_amount', 15, 2)->default(0);
            $t->decimal('shipping_charges', 15, 2)->default(0);
            $t->decimal('other_charges', 15, 2)->default(0);
            $t->decimal('total_amount', 15, 2)->default(0);
            $t->decimal('amount_paid', 15, 2)->default(0);
            $t->timestamp('received_at')->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
            $t->index('status');
            $t->index('purchase_date');
        });

        Schema::create('purchase_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('quantity');
            $t->unsignedInteger('received_qty')->default(0);
            $t->decimal('unit_cost', 15, 2);            // excluding GST
            $t->decimal('gst_rate', 5, 2)->default(0);
            $t->decimal('line_subtotal', 15, 2);
            $t->decimal('gst_amount', 15, 2);
            $t->decimal('line_total', 15, 2);
            $t->timestamps();
            $t->index('product_id');
        });

        // One row per product: current stock buckets + thresholds. Changed ONLY via InventoryService.
        Schema::create('inventory', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->unique()->constrained()->restrictOnDelete();
            $t->integer('available_qty')->default(0);
            $t->integer('reserved_qty')->default(0);
            $t->integer('damaged_qty')->default(0);
            $t->integer('returned_qty')->default(0);
            $t->integer('in_transit_qty')->default(0);
            $t->unsignedInteger('minimum_stock')->default(0);
            $t->unsignedInteger('reorder_level')->default(0);
            $t->timestamps();
        });

        // Immutable ledger (no updated_at, never edited/deleted).
        Schema::create('inventory_transactions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->string('type', 20)->index();
            $t->integer('qty_change');                  // delta on available_qty
            $t->integer('damaged_change')->default(0);  // delta on damaged_qty
            $t->integer('balance_after');               // available_qty after this row
            $t->decimal('unit_cost', 15, 2)->nullable();
            $t->nullableMorphs('reference');            // purchase / order / return ...
            $t->string('note')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions');
        Schema::dropIfExists('inventory');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('suppliers');
    }
};
