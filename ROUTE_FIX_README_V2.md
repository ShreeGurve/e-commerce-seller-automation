# Seller Automation route fix V2

The Automation route was already registered. The previous patch did not add Notifications and Approval routes because the Automation route existed already. This update adds those missing routes and controller imports.

## Replace
Replace `routes/web.php` with the included file.

## Clear and verify
```bash
php artisan optimize:clear
php artisan route:list --name=automation
php artisan route:list --name=notifications
php artisan route:list --name=approvals
```

Expected:
- `automation.index` and automation rule actions
- `notifications.index`, `notifications.read`, `notifications.read-all`
- `approvals.index`, `approvals.store`, `approvals.approve`, `approvals.reject`, `approvals.cancel`

No migration is required for this route patch. Do not run `migrate:fresh`.
