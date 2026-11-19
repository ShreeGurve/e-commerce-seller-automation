[CmdletBinding()]
param(
    [switch]$RunPackageMigrations
)

$ErrorActionPreference = 'Stop'
$ProjectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $ProjectRoot

Write-Host 'Seller Automation - Package 18/19 database connection repair' -ForegroundColor Cyan
Write-Host "Project: $ProjectRoot"

# These variables were previously set for SQLite test runs. Process-level values
# override .env, so remove them before invoking Artisan against the MySQL app DB.
foreach ($name in @('DB_CONNECTION', 'DB_DATABASE', 'DB_URL')) {
    if (Test-Path "Env:$name") {
        Remove-Item "Env:$name" -ErrorAction SilentlyContinue
        Write-Host "Removed temporary process override: $name" -ForegroundColor Yellow
    }
}

php artisan config:clear
if ($LASTEXITCODE -ne 0) { throw 'php artisan config:clear failed.' }

$diagnostic = php artisan tinker --execute="dump('CONNECTION='.config('database.default')); dump('DATABASE='.DB::connection()->getDatabaseName());"
if ($LASTEXITCODE -ne 0) { throw 'Could not read Laravel database configuration.' }
$diagnostic | ForEach-Object { Write-Host $_ }

$diagText = ($diagnostic | Out-String)
if ($diagText -notmatch 'CONNECTION=mysql' -or $diagText -notmatch 'DATABASE=laravel_seller_automation') {
    throw 'STOP: Laravel is not connected to MySQL database laravel_seller_automation. Check .env and any Windows User/Machine DB_* environment variables. No migrations were run.'
}

Write-Host ''
Write-Host 'Checking migration repository...' -ForegroundColor Cyan
php artisan migrate:status
if ($LASTEXITCODE -ne 0) {
    throw 'STOP: MySQL migration repository is missing or unreadable. No Package 18/19 migrations were run. Inspect the database migration history before creating or rebuilding it.'
}

if (-not $RunPackageMigrations) {
    Write-Host ''
    Write-Host 'Connection is correct and migration status is readable.' -ForegroundColor Green
    Write-Host 'Review the two Package 18/19 migration rows above. To apply only those packages, rerun with -RunPackageMigrations.' -ForegroundColor Yellow
    exit 0
}

Write-Host ''
Write-Host 'Applying only Package 18 migration...' -ForegroundColor Cyan
php artisan migrate --path=database/migrations/2026_11_19_000011_create_marketplace_automation_rules_tables.php --force
if ($LASTEXITCODE -ne 0) { throw 'Package 18 migration failed. Stop and inspect the Laravel error before continuing.' }

Write-Host 'Applying only Package 19 migration...' -ForegroundColor Cyan
php artisan migrate --path=database/migrations/2026_11_19_000012_create_marketplace_automation_execution_tables.php --force
if ($LASTEXITCODE -ne 0) { throw 'Package 19 migration failed. Stop and inspect the Laravel error before continuing.' }

Write-Host ''
Write-Host 'Verifying Package 18/19 migration status...' -ForegroundColor Cyan
php artisan migrate:status
if ($LASTEXITCODE -ne 0) { throw 'Could not verify migration status after applying Package 18/19.' }

Write-Host ''
Write-Host 'Package 18/19 migration run completed. Confirm tables in phpMyAdmin before using the dashboards.' -ForegroundColor Green
