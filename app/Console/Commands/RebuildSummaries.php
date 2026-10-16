<?php
namespace App\Console\Commands;

use App\Services\SummaryService;
use Illuminate\Console\Command;

class RebuildSummaries extends Command
{
    protected $signature = 'summary:rebuild {--from= : first date (YYYY-MM-DD)} {--to= : last date (YYYY-MM-DD)}';
    protected $description = 'Rebuild the daily sales / profit / advertising summary tables (dashboard speed tables) from the real data';

    public function handle(SummaryService $s): int
    {
        $n = ($this->option('from') && $this->option('to')) ? $s->rebuildRange($this->option('from'), $this->option('to')) : $s->rebuildAll();
        $this->info("Rebuilt {$n} day/channel summaries.");
        return self::SUCCESS;
    }
}
