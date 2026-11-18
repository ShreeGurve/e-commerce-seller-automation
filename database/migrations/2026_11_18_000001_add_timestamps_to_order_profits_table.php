<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Add the Eloquent timestamp columns without altering existing profit data.
     * Safe for databases where one or both columns already exist.
     */
    public function up(): void
    {
        if (! Schema::hasTable('order_profits')) {
            return;
        }

        Schema::table('order_profits', function (Blueprint $table) {
            if (! Schema::hasColumn('order_profits', 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }
            if (! Schema::hasColumn('order_profits', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });
    }

    /**
     * Intentionally keep the columns on rollback to avoid removing audit data.
     */
    public function down(): void
    {
        // Non-destructive: timestamps may already contain operational history.
    }
};
