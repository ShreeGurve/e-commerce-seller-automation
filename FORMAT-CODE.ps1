$ErrorActionPreference = "Stop"

if (-not (Test-Path "vendor/bin/pint")) {
    Write-Host "Laravel Pint is not installed. Run: composer install" -ForegroundColor Yellow
    exit 1
}

Write-Host "Formatting PHP and Blade files with the Laravel Pint preset..."
php vendor/bin/pint app bootstrap config database routes tests

Write-Host "Formatting completed. Review the changes with: git diff"
