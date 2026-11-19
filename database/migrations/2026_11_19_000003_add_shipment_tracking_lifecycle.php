<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('order_fulfillments')) {
            return;
        }

        Schema::table('order_fulfillments', function (Blueprint $table) {
            if (! Schema::hasColumn('order_fulfillments', 'tracking_status')) {
                $table->string('tracking_status', 40)->nullable();
            }
            if (! Schema::hasColumn('order_fulfillments', 'last_tracking_at')) {
                $table->timestamp('last_tracking_at')->nullable();
            }
            if (! Schema::hasColumn('order_fulfillments', 'delivery_exception')) {
                $table->string('delivery_exception', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('order_fulfillments')) {
            return;
        }

        Schema::table('order_fulfillments', function (Blueprint $table) {
            foreach (['tracking_status', 'last_tracking_at', 'delivery_exception'] as $column) {
                if (Schema::hasColumn('order_fulfillments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
