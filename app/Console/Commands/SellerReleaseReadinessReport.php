<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SellerReleaseReadinessReport extends Command
{
    protected $signature = 'seller-automation:release-readiness {--output= : Optional path for a Markdown report}';
    protected $description = 'Generate a read-only Seller Automation release readiness summary';

    public function handle(): int
    {
        $checks = [];
        $add = function (string $name, string $status, string $detail) use (&$checks): void {
            $checks[] = compact('name', 'status', 'detail');
        };

        $add('Application key', filled(config('app.key')) ? 'PASS' : 'FAIL', 'APP_KEY must be set before deployment.');
        $add('Debug mode', config('app.debug') ? 'WARN' : 'PASS', 'APP_DEBUG should be false in production.');
        $add('HTTPS URL', str_starts_with((string) config('app.url'), 'https://') ? 'PASS' : 'WARN', 'Use an HTTPS APP_URL for production.');
        $add('Database connection', filled(config('database.default')) ? 'PASS' : 'FAIL', 'Default database connection configuration.');
        $add('Migration history', Schema::hasTable('migrations') ? 'PASS' : 'FAIL', 'The migrations table must exist.');
        $add('Marketplace API write access', 'REVIEW', 'Keep live listing, inventory, order, label and pickup actions disabled until marketplace approvals and end-to-end tests are complete.');
        $add('Financial TEST-order exclusion', 'REVIEW', 'Verify TEST orders are excluded from revenue, profit, settlement and tax reports.');
        $add('Backup and restore drill', 'REVIEW', 'Take a database and file backup, then verify restoration in a separate environment.');
        $add('Scheduler', 'REVIEW', 'Configure the Laravel scheduler to run every minute on the production host.');
        $add('Queue workers', config('queue.default') === 'sync' ? 'WARN' : 'REVIEW', 'Confirm the selected queue driver and supervised workers match production requirements.');
        $add('Automated tests', 'REVIEW', 'Run the project test suite and resolve failures before release.');

        try {
            DB::connection()->getPdo();
            $add('Database connectivity', 'PASS', 'The configured database connection responded successfully.');
        } catch (Throwable $e) {
            $add('Database connectivity', 'FAIL', 'Connection failed: '.$e->getMessage());
        }

        $lines = ['# Seller Automation — Release Readiness', '', 'Generated: '.now()->toDateTimeString(), '', '| Check | Status | Detail |', '|---|---|---|'];
        foreach ($checks as $check) {
            $safe = fn ($value) => str_replace(['|', "\r", "\n"], ['\\|', ' ', ' '], $value);
            $lines[] = '| '.$safe($check['name']).' | **'.$safe($check['status']).'** | '.$safe($check['detail']).' |';
        }
        $lines[] = '';
        $lines[] = '> This report is advisory. REVIEW items require human verification; it does not certify marketplace approval or production safety.';
        $report = implode(PHP_EOL, $lines).PHP_EOL;
        $this->line($report);

        if ($path = $this->option('output')) {
            $target = str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:\\\\/', $path)
                ? $path : base_path($path);
            $directory = dirname($target);
            if (!is_dir($directory) || !is_writable($directory)) {
                $this->error('Output directory does not exist or is not writable: '.$directory);
                return self::FAILURE;
            }
            file_put_contents($target, $report);
            $this->info('Report written to '.$target);
        }

        $failures = count(array_filter($checks, fn ($item) => $item['status'] === 'FAIL'));
        return $failures ? self::FAILURE : self::SUCCESS;
    }
}
