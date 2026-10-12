# SHREE GURVE — Step 1 Setup (WAMP, runs directly in browser via Apache)

You never run `php artisan serve`. Apache serves the app. `php artisan` is used only for one-time
tasks (migrate / seed / key) from a terminal.

## Requirements
WAMP with PHP 8.2+ (check: WAMP menu → PHP → Version), MySQL, Apache with `rewrite_module` ON
(WAMP menu → Apache → Apache modules → rewrite_module ✔), and Composer for Windows.

## 1. Create the Laravel project (once)
```
cd C:\wamp64\www\laravel
composer create-project laravel/laravel seller-automation
```

## 2. Copy this package over the project
Extract `shreegurve-step1.zip` into `C:\wamp64\www\laravel\seller-automation`, choose **Replace/merge**.
It overwrites only: `app/`, `database/migrations`, `database/seeders`, `resources/views`, `routes/web.php`.
(It replaces Laravel's default `0001_01_01_000000_create_users_table.php` on purpose.)

## 3. Database
phpMyAdmin → New → name `shreegurve` → collation `utf8mb4_unicode_ci`.

## 4. Edit `.env`
```
APP_NAME="SHREE GURVE"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1/laravel/seller-automation
APP_TIMEZONE=Asia/Kolkata

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=shreegurve
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
```

## 5. URL (no virtual host)
Project folder: `C:\wamp64\www\laravel\seller-automation`
Open: **http://127.0.0.1/laravel/seller-automation/**
The root `index.php` + `.htaccess` (included in the package) make this work. Make sure
Apache `rewrite_module` is ON and restart WAMP once.
(When you later move to a VPS, point the web root to `public/` and delete the root `index.php`/`.htaccess`.)

## 6. One-time commands (Command Prompt in the project folder)
```
cd C:\wamp64\www\laravel\seller-automation
php artisan key:generate
php artisan migrate:fresh --seed
php artisan optimize:clear
```

## 7. Open in browser
http://127.0.0.1/laravel/seller-automation/ → login `admin@shreegurve.local` / `ChangeMe@123` → **change this password first.**

## 8. Verification checklist
1. Login works, dashboard shows 4 counters (Marketplaces = 2).
2. Categories → add one. Products → New product with COGS 120, Packaging 10.
3. Product → Costs tab → set COGS 135 effective today+1 day → table shows **both** 120 (closed) and 135 (current).
4. Product → Marketplace tab → map an Amazon SKU; try the same SKU again → rejected (unique).
5. History tab shows every action; sidebar "Soon" modules open placeholder pages.
6. Phpmyadmin: `activity_logs` has rows; money columns are DECIMAL(15,2).

## Troubleshooting
- 404 on every page except `/`: rewrite_module off or `AllowOverride All` missing.
- 500 error: check `storage\logs\laravel.log`; ensure `storage` and `bootstrap\cache` are writable.
- "No application encryption key": run `php artisan key:generate`.
- Pages unstyled: Bootstrap loads from CDN, so internet is needed for now.
