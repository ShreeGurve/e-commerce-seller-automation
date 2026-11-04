<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('marketplace_connections', function (Blueprint $table) {
            // First provide an alternative index for the business_id foreign key.
            $table->index(
                'business_id',
                'marketplace_connections_business_id_index'
            );
        });

        // Now safely replace the old unique index.
        Schema::table('marketplace_connections', function (Blueprint $table) {
            $table->dropUnique(
                'marketplace_connection_business_platform_unique'
            );

            $table->unique(
                ['business_id', 'marketplace_id', 'environment'],
                'marketplace_connection_business_platform_environment_unique'
            );
        });

    }

    public function down(): void
    {
        Schema::table('marketplace_connections', function (Blueprint $table) {
            $table->dropUnique('marketplace_connection_business_platform_env_unique');
            $table->unique(['business_id', 'marketplace_id'], 'marketplace_connection_business_platform_unique');
            $table->dropColumn('environment');
        });
    }
};
