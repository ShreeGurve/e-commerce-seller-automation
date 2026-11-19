# Seller Automation — Package 3 Migration Fix

## What this fixes

Replaces Laravel-generated foreign-key names with short explicit names in migration `2026_11_19_000001_create_marketplace_read_sync_tables.php`. This avoids MySQL error 1059 (identifier name too long), including on `marketplace_inventory_snapshots`.

## Important: clean up the partial migration first

The failed migration may have created the first two tables before MySQL rejected the third table. Because MySQL DDL is not fully transactional, the migration can be absent from the `migrations` table while some of its tables already exist.

1. Back up the database.
2. Confirm this migration is NOT recorded:

```sql
SELECT migration FROM migrations
WHERE migration = '2026_11_19_000001_create_marketplace_read_sync_tables';
```

3. If the query returns no row, remove only the partial tables created by this migration (if present):

```sql
DROP TABLE IF EXISTS marketplace_inventory_snapshots;
DROP TABLE IF EXISTS marketplace_sync_records;
DROP TABLE IF EXISTS marketplace_sync_runs;
```

Do not run those DROP statements if the migration is already recorded or if these tables contain data you need. Stop and inspect before proceeding in that case.

## Install

1. Extract this ZIP into the Laravel project root, allowing the migration file to be replaced.
2. Run:

```powershell
php artisan optimize:clear
php artisan migrate --pretend
php artisan migrate
```

3. Verify:

```sql
SHOW TABLES LIKE 'marketplace_%';
```

Then run:

```powershell
php artisan test --filter=MarketplaceReadSyncServiceTest
php artisan test
```

This patch changes only foreign-key naming in the Package 3 migration; it does not change sync service behavior or enable live marketplace API calls.
