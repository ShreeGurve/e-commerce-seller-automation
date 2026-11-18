# Seller Automation audit fix — AdvertisingTest PHPUnit compatibility

## Problem
`Tests\Feature\AdvertisingTest` declared a private helper named `run()`. The class extends Laravel's `Tests\TestCase`, which extends PHPUnit's `PHPUnit\Framework\TestCase`. In the installed PHPUnit 12 version, `TestCase::run()` is final, so PHP fatals while loading the test class.

## Fix
Renamed the test-only helper from `run()` to `processImport()` and updated all its call sites. The helper still delegates to `ImportProcessor::run()`; production application code is unchanged.

## Install
1. Back up `tests/Feature/AdvertisingTest.php`.
2. Extract this ZIP into the Laravel project root, overwriting that one file.
3. Run:

```bash
php artisan test --filter=AdvertisingTest
```

Then run the full suite:

```bash
php artisan test
```

The test suite's `phpunit.xml` configures SQLite in-memory. Keep this test configuration; do not point `RefreshDatabase` tests at your real MySQL database.

## Validation performed here
- Confirmed the original test helper and all call sites in the uploaded project archive.
- Confirmed no other test file in the uploaded archive declares a method named `run()`.
- PHP syntax and ZIP integrity checks are performed on this patch.
- Full PHPUnit execution requires the user's local `vendor/` installation and is not claimed here.
