<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('order_fulfillments')) {
            Schema::create('order_fulfillments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
                $table->string('package_reference', 80)->nullable();
                $table->decimal('weight_kg', 8, 3)->nullable();
                $table->decimal('length_cm', 8, 2)->nullable();
                $table->decimal('width_cm', 8, 2)->nullable();
                $table->decimal('height_cm', 8, 2)->nullable();
                $table->string('carrier', 100)->nullable();
                $table->string('service_level', 80)->nullable();
                $table->string('tracking_number', 120)->nullable();
                $table->string('tracking_url', 500)->nullable();
                $table->string('invoice_number', 100)->nullable();
                $table->string('label_path')->nullable();
                $table->string('invoice_path')->nullable();
                $table->string('pickup_reference', 120)->nullable();
                $table->dateTime('pickup_scheduled_at')->nullable();
                $table->dateTime('picked_up_at')->nullable();
                $table->text('packing_notes')->nullable();
                $table->foreignId('packed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('packed_at')->nullable();
                $table->foreignId('dispatched_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('order_fulfillment_events')) {
            Schema::create('order_fulfillment_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                $table->string('event_type', 40);
                $table->string('description', 255)->nullable();
                $table->json('meta')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['order_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_fulfillment_events');
        Schema::dropIfExists('order_fulfillments');
    }
};
