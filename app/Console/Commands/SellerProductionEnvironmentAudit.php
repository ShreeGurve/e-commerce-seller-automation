<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class SellerProductionEnvironmentAudit extends Command
{
    protected $signature = 'seller-automation:environment-audit {--strict : Return a failing exit code for warnings as well as failures}';
    protected $description = 'Read-only production environment and deployment configuration audit';

    public function handle(): int
    {
        $issues = 0;
        $warnings = 0;
        $this->info('Seller Automation — Environment Audit (read-only)');
        $this->line('');

        $check = function (string $label, bool $ok, string $detail, bool $warning = false) use (&$issues, &$warnings): void {
            if ($ok) {
                $this->components->twoColumnDetail($label, '<fg=green>PASS</> '.$detail);
            } else {
                $warning ? $warnings++ : $issues++;
                $tag = $warning ? '<fg=yellow>WARN</>' : '<fg=red>FAIL</>';
                $this->components->twoColumnDetail($label, $tag.' '.$detail);
            }
        };

        $check('Application key', filled(config('app.key')), 'APP_KEY is configured');
        $isProduction = app()->environment('production');
        $check('Application environment', $isProduction, 'APP_ENV='.app()->environment(), true);
        $check('Debug mode', !config('app.debug'), 'APP_DEBUG is disabled', true);
        $url = (string) config('app.url');
        $check('Application URL', str_starts_with($url, 'https://'), $url !== '' ? $url : 'APP_URL is empty', true);
        $check('Database connection', filled(config('database.default')), 'Default connection: '.config('database.default'));
        $check('Queue driver', config('queue.default') !== 'sync', 'QUEUE_CONNECTION='.config('queue.default'), true);
        $check('Session driver', filled(config('session.driver')), 'SESSION_DRIVER='.config('session.driver'));
        $check('Cache driver', filled(config('cache.default')), 'CACHE_STORE='.config('cache.default'));

        $storagePath = storage_path();
        $check('Storage writable', is_dir($storagePath) && is_writable($storagePath), $storagePath);
        $logPath = storage_path('logs');
        $check('Log directory writable', is_dir($logPath) && is_writable($logPath), $logPath);
        $publicStorage = public_path('storage');
        $check('Public storage link', is_link($publicStorage) || is_dir($publicStorage), 'Run php artisan storage:link if uploads are public', true);

        $this->line('');
        $this->info("Failures: {$issues} | Warnings: {$warnings}");
        $this->comment('This command does not change configuration, files, credentials or marketplace data.');
        if ($issues > 0 || ($this->option('strict') && $warnings > 0)) {
            return self::FAILURE;
        }
        return self::SUCCESS;
    }
}
