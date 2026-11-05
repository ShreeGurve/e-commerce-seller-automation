# Package 7 — Blade ParseError Repair

## Error fixed
`resources/views/layouts/app.blade.php` contained literal `\n` characters between the Automation Control Center and Notifications menu entries. PHP interpreted those characters as source text and Blade compilation failed with a ParseError.

## Install
1. Back up `resources/views/layouts/app.blade.php`.
2. Extract this ZIP into the Laravel project root and replace that file.
3. Run:

```bash
php artisan view:clear
php artisan optimize:clear
```

4. Refresh `/automation/control-center`.

## Validation
The corrected Blade/PHP file passed `php -l`. This is a targeted one-file repair; no migrations or database changes are included.
