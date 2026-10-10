<?php
namespace App\Console\Commands;

use App\Services\ProfitService;
use Illuminate\Console\Command;

class RecalculateProfit extends Command
{
    protected $signature = 'profit:recalculate';
    protected $description = 'Recalculate estimated profit for every order (run after changing fee rules or settings)';

    public function handle(ProfitService $profit): int
    {
        $this->info('Recalculating...');
        $n = $profit->recalcAll();
        $this->info("Done: {$n} orders.");
        return self::SUCCESS;
    }
}
