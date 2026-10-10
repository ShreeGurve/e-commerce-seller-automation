<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('marketplace_id')->constrained()->restrictOnDelete();
            $t->string('external_order_id', 60);              // marketplace order id (idempotency key)
            $t->string('order_number', 30)->unique();         // internal SG-2026-000001
            $t->dateTime('order_date');
            $t->string('status', 15)->default('CONFIRMED')->index();
            $t->string('marketplace_status', 60)->nullable(); // raw status from marketplace
            $t->string('fulfillment_type', 15)->default('SELF'); // SELF | MARKETPLACE
            $t->string('customer_ref', 120)->nullable();      // order/customer reference only, no personal data
            $t->string('ship_state', 80)->nullable();
            $t->decimal('subtotal', 15, 2)->default(0);       // items after discount, GST inclusive
            $t->decimal('shipping_charged', 15, 2)->default(0);
            $t->decimal('tax_amount', 15, 2)->default(0);     // GST contained in subtotal
            $t->decimal('total_amount', 15, 2)->default(0);
            $t->string('source', 10)->default('MANUAL');      // MANUAL | CSV | API
            $t->unsignedBigInteger('import_id')->nullable();  // FK added with the import engine (Phase 3)
            $t->timestamp('shipped_at')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['marketplace_id', 'external_order_id']);   // repeated import can never duplicate
            $t->index('order_date');
        });

        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->restrictOnDelete();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->string('external_line_id', 60)->nullable();
            $t->string('marketplace_sku', 120)->nullable();
            $t->unsignedInteger('quantity');
            $t->unsignedInteger('qty_deducted')->default(0);       // stock actually taken from inventory
            $t->decimal('unit_price', 15, 2);                       // selling price per unit, GST inclusive
            $t->decimal('discount', 15, 2)->default(0);             // line discount
            $t->decimal('gst_rate', 5, 2)->default(0);
            $t->decimal('tax_amount', 15, 2)->default(0);           // GST inside line_total
            $t->decimal('line_total', 15, 2);
            // Cost SNAPSHOT per unit on order date. Later cost changes never rewrite old profit.
            $t->decimal('cogs_at_sale', 15, 2)->default(0);
            $t->decimal('packaging_at_sale', 15, 2)->default(0);
            $t->decimal('other_cost_at_sale', 15, 2)->default(0);
            $t->timestamps();
            $t->unique(['order_id', 'external_line_id']);
            $t->index('product_id');
        });

        Schema::create('order_status_history', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->string('from_status', 15)->nullable();
            $t->string('to_status', 15);
            $t->string('note')->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::create('returns', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->restrictOnDelete();
            $t->foreignId('marketplace_id')->constrained()->restrictOnDelete();
            $t->string('return_number', 30)->unique();
            $t->string('external_return_id', 60)->nullable();
            $t->date('return_date');
            $t->string('return_type', 25)->default('UNKNOWN');
            $t->string('reason_text')->nullable();
            $t->string('status', 12)->default('REQUESTED')->index();   // REQUESTED | INSPECTED | CLOSED
            $t->timestamp('inspected_at')->nullable();
            $t->boolean('claim_eligible')->default(false);
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['marketplace_id', 'external_return_id']);
        });

        Schema::create('return_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('return_id')->constrained('returns')->restrictOnDelete();
            $t->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('quantity');                    // expected
            $t->unsignedInteger('received_qty')->default(0);    // actually received
            $t->string('condition', 10)->nullable();            // SELLABLE|USED|DAMAGED|SCRAP|MISSING
            $t->unsignedInteger('restocked_qty')->default(0);
            $t->string('note')->nullable();
            $t->timestamps();
        });

        Schema::create('return_media', function (Blueprint $t) {
            $t->id();
            $t->foreignId('return_id')->constrained('returns')->cascadeOnDelete();
            $t->string('path');
            $t->string('original_name');
            $t->string('mime', 60);
            $t->unsignedInteger('size');
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('refunds', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->restrictOnDelete();
            $t->foreignId('return_id')->nullable()->constrained('returns')->nullOnDelete();
            $t->string('external_refund_id', 60)->nullable();
            $t->decimal('amount', 15, 2);
            $t->date('refund_date');
            $t->string('reason')->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['order_id', 'external_refund_id']);
        });
    }

    public function down(): void
    {
        foreach (['refunds', 'return_media', 'return_items', 'returns', 'order_status_history', 'order_items', 'orders'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
