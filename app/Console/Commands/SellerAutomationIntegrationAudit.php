<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SellerAutomationIntegrationAudit extends Command
{
    protected $signature = 'seller-automation:integration-audit
                            {--skip-database : Check routes and source safeguards without querying the database}';
    protected $description = 'Run a read-only integration readiness audit for Seller Automation modules';

    public function handle(): int
    {
        $checks = [];
        $requiredRoutes = [
            'purchase-invoice-matching.index',
            'purchase-invoice-line-checks.index',
            'purchase-invoice-documents.index',
            'supplier-payments.index',
            'supplier-credit-notes.index',
            'supplier-statements.index',
            'procurement.index',
            'warehouse.intelligence.index',
            'approval-workflows.index',
            'governance.control-center',
            'operational-insights.index',
            'automation.index',
            'automation.store',
            'automation.update',
            'automation.preview',
            'automation.retry',
            'automation.run',
        ];

        foreach ($requiredRoutes as $name) {
            $checks[] = [
                'area' => 'Route',
                'check' => $name,
                'ok' => Route::has($name),
                'detail' => Route::has($name) ? 'Registered' : 'Missing route',
            ];
        }

        $sourceChecks = [
            'Automation dry-run evaluator' => [app_path('Services/AutomationService.php'), 'function previewRule'],
            'TEST order exclusion in automation profit alerts' => [app_path('Services/AutomationService.php'), "where('source','<>','TEST')"],
            'TEST order exclusion in reporting' => [app_path('Services/ReportService.php'), "where('o.source', '<>', 'TEST')"],
            'Automation version model' => [app_path('Models/AutomationRuleVersion.php'), 'class AutomationRuleVersion'],
            'Automation execution model' => [app_path('Models/AutomationExecution.php'), 'class AutomationExecution'],
        ];

        foreach ($sourceChecks as $label => [$path, $needle]) {
            $contents = is_file($path) ? file_get_contents($path) : false;
            $ok = $contents !== false && str_contains($contents, $needle);
            $checks[] = [
                'area' => 'Source safeguard',
                'check' => $label,
                'ok' => $ok,
                'detail' => $ok ? 'Expected implementation marker found' : 'File or expected implementation marker missing',
            ];
        }

        if (!$this->option('skip-database')) {
            $requiredTables = [
                'purchases',
                'purchase_invoice_matches',
                'purchase_invoice_line_checks',
                'supplier_invoice_payments',
                'supplier_credit_notes',
                'procurement_requisitions',
                'procurement_supplier_quotes',
                'warehouse_locations',
                'warehouse_stock_balances',
                'warehouse_transfers',
                'warehouse_stock_counts',
                'approval_requests',
                'business_automation_events',
                'approval_sla_policies',
                'business_activity_logs',
                'automation_rule_versions',
                'automation_executions',
            ];

            try {
                DB::connection()->getPdo();
                foreach ($requiredTables as $table) {
                    $ok = Schema::hasTable($table);
                    $checks[] = [
                        'area' => 'Database table',
                        'check' => $table,
                        'ok' => $ok,
                        'detail' => $ok ? 'Present' : 'Missing — check migrations',
                    ];
                }
            } catch (Throwable $e) {
                $checks[] = [
                    'area' => 'Database connection',
                    'check' => config('database.default'),
                    'ok' => false,
                    'detail' => 'Could not inspect database: '.$e->getMessage(),
                ];
            }
        }

        $this->newLine();
        $this->table(
            ['Area', 'Check', 'Result', 'Details'],
            array_map(fn ($item) => [
                $item['area'],
                $item['check'],
                $item['ok'] ? '<fg=green>PASS</>' : '<fg=red>FAIL</>',
                $item['detail'],
            ], $checks)
        );

        $passed = count(array_filter($checks, fn ($item) => $item['ok']));
        $failed = count($checks) - $passed;
        $this->newLine();
        $this->info("Integration audit: {$passed} passed, {$failed} failed, ".count($checks).' total checks.');

        if ($failed > 0) {
            $this->warn('This audit is read-only. It does not modify records or run workflow actions.');
            return self::FAILURE;
        }

        $this->info('All configured readiness checks passed. Continue with the manual workflow checklist.');
        return self::SUCCESS;
    }
}
