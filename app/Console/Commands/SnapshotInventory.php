<?php
namespace App\Console\Commands;

use App\Services\SummaryService;
use Illuminate\Console\Command;

class SnapshotInventory extends Command
{
    protected $signature = 'summary:snapshot';
    protected $description = 'Save today\'s stock level of every product into daily_inventory';

    public function handle(SummaryService $s): int
    {
        $this->info('Saved ' . $s->snapshotInventory() . ' stock rows.');
        return self::SUCCESS;
    }
}
