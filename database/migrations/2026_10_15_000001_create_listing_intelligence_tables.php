<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Compatibility placeholder.
 *
 * The original version of this migration duplicated the listings,
 * listing_versions, keywords and keyword_rankings tables created by
 * create_listing_tables.php. It also duplicated price_history, which is
 * owned by the pricing migration (2026_10_16_000001).
 *
 * Keep this migration filename so Laravel installations that already have
 * it recorded in the migrations table remain compatible. The canonical
 * listing schema is created by 2026_10_15_000001_create_listing_tables.php.
 */
return new class extends Migration {
    public function up(): void
    {
        // Intentionally empty. See the canonical listing migration.
    }

    public function down(): void
    {
        // Intentionally empty. The canonical migration owns these tables.
    }
};
