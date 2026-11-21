<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use ZipArchive;

class SellerBackupIntegrityAudit extends Command
{
    protected $signature = 'seller-automation:backup-audit {path? : Existing backup archive or directory to inspect}';

    protected $description = 'Create a read-only integrity summary for an existing backup archive or directory';

    public function handle(): int
    {
        $path = $this->argument('path') ?: 'storage/app/backups';
        $path = str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\/]/', $path)
            ? $path : base_path($path);

        if (is_file($path)) {
            $this->line('Backup file: '.$path);
            $this->line('Size: '.number_format(filesize($path)).' bytes');
            $this->line('SHA-256: '.hash_file('sha256', $path));
            if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'zip') {
                if (! class_exists(ZipArchive::class)) {
                    $this->warn('ZIP extension is unavailable; archive contents could not be checked.');

                    return self::SUCCESS;
                }
                $zip = new ZipArchive;
                if ($zip->open($path, ZipArchive::CHECKCONS) !== true) {
                    $this->error('ZIP archive failed integrity/open validation.');

                    return self::FAILURE;
                }
                $this->info('ZIP integrity: readable');
                $this->line('Entries: '.$zip->numFiles);
                $zip->close();
            } else {
                $this->comment('File hash recorded. Content/restore validity is not established for this file type.');
            }

            return self::SUCCESS;
        }

        if (! is_dir($path)) {
            $this->warn('No backup directory found at: '.$path);
            $this->line('This audit does not create backup folders or empty backups.');
            $this->comment('Provide the path to an existing backup directory or archive, for example:');
            $this->line('  php artisan seller-automation:backup-audit storage/app');
            $this->line('  php artisan seller-automation:backup-audit "S:/Devotion/backups/seller-automation.zip"');
            $this->comment('Create or configure a real backup first, then rerun this audit against its actual location.');

            return self::SUCCESS;
        }

        $count = 0;
        $bytes = 0;
        $hash = hash_init('sha256');
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile() || $file->isLink()) {
                continue;
            }
            $count++;
            $bytes += $file->getSize();
            hash_update($hash, $file->getPathname()."\0".$file->getSize()."\0".hash_file('sha256', $file->getPathname())."\n");
        }

        $this->info('Backup directory: '.$path);
        $this->line('Files: '.$count);
        $this->line('Total size: '.number_format($bytes).' bytes');
        $this->line('Manifest SHA-256: '.hash_final($hash));
        $this->comment('This is an integrity fingerprint only. It does not prove database consistency, completeness, encryption, off-site retention or successful restoration. Perform a restore drill in a separate environment.');

        return $count > 0 ? self::SUCCESS : self::FAILURE;
    }
}
