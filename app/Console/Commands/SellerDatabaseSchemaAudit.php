<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SellerDatabaseSchemaAudit extends Command
{
    protected $signature = 'seller-automation:schema-audit {--strict : Fail when optional module tables are missing}';

    protected $description = 'Read-only audit of core Seller Automation database tables and migration status';

    public function handle(): int
    {
        $this->info('Seller Automation — Database Schema Audit (read-only)');
        $required = ['users', 'migrations'];
        $recommended = [
            'businesses', 'products', 'orders', 'order_items', 'inventory_movements',
            'suppliers', 'purchases', 'roles', 'permissions', 'role_user', 'permission_role',
            'automation_tasks', 'automation_task_dependencies',
        ];
        $failed = 0;
        $warnings = 0;

        foreach ($required as $table) {
            $exists = Schema::hasTable($table);
            $this->line(($exists ? '<fg=green>PASS</>' : '<fg=red>FAIL</>')." Required table: {$table}");
            if (! $exists) {
                $failed++;
            }
        }
        foreach ($recommended as $table) {
            $exists = Schema::hasTable($table);
            $this->line(($exists ? '<fg=green>FOUND</>' : '<fg=yellow>OPTIONAL</>')." Module table: {$table}");
            if (! $exists) {
                $warnings++;
            }
        }

        if (Schema::hasTable('automation_tasks')) {
            foreach (['business_id', 'title', 'status', 'priority', 'assigned_to', 'due_at'] as $column) {
                $exists = Schema::hasColumn('automation_tasks', $column);
                $this->line(($exists ? '<fg=green>PASS</>' : '<fg=red>FAIL>')." automation_tasks.{$column}");
                if (! $exists) {
                    $failed++;
                }
            }
        }

        try {
            if (Schema::hasTable('migrations')) {
                $ran = DB::table('migrations')->count();
                $this->info("Recorded migrations: {$ran}");
                $this->comment('Run php artisan migrate:status to inspect pending migrations.');
            }
        } catch (Throwable $e) {
            $this->error('Could not read migration history: '.$e->getMessage());
            $failed++;
        }

        $this->newLine();
        $this->info("Failures: {$failed} | Optional tables absent: {$warnings}");
        $this->comment('Optional tables may be absent when the corresponding feature package is not installed. No schema changes are made.');

        return ($failed > 0 || ($this->option('strict') && $warnings > 0)) ? self::FAILURE : self::SUCCESS;
    }
}
