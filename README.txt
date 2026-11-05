PHASE 25 ROUTE ORDER FIX

Cause: /purchases/{purchase} was registered before /purchases/quality-control, so Laravel interpreted "quality-control" as a purchase identifier and the model lookup returned 404.

Install: Extract this ZIP into the Laravel project root and overwrite routes/web.php. Then run:

php artisan optimize:clear
php artisan route:list --path=purchases

The specific routes for cost intelligence, quality control, and budgets are now registered before dynamic purchase routes. Purchase IDs are constrained to numeric values. No migration or database changes are required.
