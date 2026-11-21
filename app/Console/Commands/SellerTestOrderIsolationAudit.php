<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SellerTestOrderIsolationAudit extends Command
{
    protected $signature = 'seller-automation:test-order-audit';

    protected $description = 'Inspect explicit TEST-order markers without changing orders or financial records';

    public function handle(): int
    {
        $this->info('Seller Automation — TEST Order Isolation Audit (read-only)');
        if (! Schema::hasTable('orders')) {
            $this->warn('REVIEW: orders table was not found.');

            return self::SUCCESS;
        }

        $columns = Schema::getColumnListing('orders');
        $candidates = [
            'is_test' => ['type' => 'boolean'],
            'test_mode' => ['type' => 'boolean'],
            'is_test_order' => ['type' => 'boolean'],
            'order_type' => ['type' => 'text'],
            'environment' => ['type' => 'text'],
        ];
        $found = array_values(array_filter(array_keys($candidates), fn ($column) => in_array($column, $columns, true)));
        if (! $found) {
            $this->warn('REVIEW: No recognized explicit TEST-order marker was found on orders.');
            $this->line('Available columns: '.implode(', ', $columns));
            $this->comment('Do not infer TEST status from order numbers or customer names. Confirm the application’s actual test-order convention before changing financial reports.');

            return self::SUCCESS;
        }

        foreach ($found as $column) {
            try {
                $query = DB::table('orders')->whereNotNull($column);
                if (in_array($column, ['is_test', 'test_mode', 'is_test_order'], true)) {
                    $count = (clone $query)->whereIn($column, [1, true, '1', 'true'])->count();
                    $this->line("Marker {$column}: {$count} TEST-marked order(s) detected (non-null total: ".(clone $query)->count().')');
                } else {
                    $values = (clone $query)->select($column)->distinct()->limit(30)->pluck($column)->map(fn ($value) => (string) $value)->all();
                    $testValues = array_values(array_filter($values, fn ($value) => in_array(strtolower(trim($value)), ['test', 'testing', 'sandbox', 'demo'], true)));
                    $count = $testValues ? DB::table('orders')->whereIn($column, $testValues)->count() : 0;
                    $this->line("Marker {$column}: ".($testValues ? implode(', ', $testValues).' => '.$count.' matching order(s)' : 'no recognized TEST value among sampled distinct values'));
                }
            } catch (Throwable $e) {
                $this->warn("REVIEW: Could not inspect {$column}: ".$e->getMessage());
            }
        }

        $this->newLine();
        $this->comment('This audit only discovers and counts explicit order markers. It does not inspect every revenue/profit/settlement/tax query and cannot certify financial exclusion. Verify each financial report and export separately. No data is modified.');

        return self::SUCCESS;
    }
}
