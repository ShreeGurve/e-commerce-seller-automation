<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Existing connections become Production connections by default.
        Schema::table('marketplace_connections', function (Blueprint $table) {
            $table->string('environment', 20)->default('production')->after('marketplace_id');
        });

        // Preserve an index for the business foreign key before replacing the
        // original (business_id, marketplace_id) unique index.
        Schema::table('marketplace_connections', function (Blueprint $table) {
            $table->index('business_id', 'marketplace_connections_business_id_index');
        });

        Schema::table('marketplace_connections', function (Blueprint $table) {
            $table->dropUnique('marketplace_connection_business_platform_unique');
            $table->unique(
                ['business_id', 'marketplace_id', 'environment'],
                'marketplace_connection_business_platform_environment_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_connections', function (Blueprint $table) {
            $table->dropUnique('marketplace_connection_business_platform_environment_unique');
            $table->unique(
                ['business_id', 'marketplace_id'],
                'marketplace_connection_business_platform_unique'
            );
            $table->dropIndex('marketplace_connections_business_id_index');
            $table->dropColumn('environment');
        });
    }
};
