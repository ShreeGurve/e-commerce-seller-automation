# Seller Automation route fix

## Fixed
- Registered the Automation routes referenced by the sidebar and automation views.
- Registered the existing Notifications routes referenced by the top-bar icon and notifications view.
- Registered the existing Approval workflow routes referenced by the sidebar and approvals view.
- Added controller imports for these existing controllers.

## Files
- `routes/web.php`

## Apply
Replace your project's `routes/web.php` with the included file, then run:

```bash
php artisan optimize:clear
php artisan route:list --name=automation
php artisan route:list --name=notifications
php artisan route:list --name=approvals
```

No database migration is required. The automation permissions (`automation.view`, `automation.manage`) and approval permissions (`approvals.view`, `approvals.decide`) are already declared by `RolesPermissionsSeeder.php`. If those permissions have not yet been seeded in your database, run:

```bash
php artisan db:seed --class=RolesPermissionsSeeder
```

Do not run `migrate:fresh`; it would delete existing data.
