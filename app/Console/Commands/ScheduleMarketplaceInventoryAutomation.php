<?php

namespace App\Console\Commands;

use App\Services\MarketplaceInventoryAutomationService;
use Illuminate\Console\Command;

class ScheduleMarketplaceInventoryAutomation extends Command
{
    protected $signature = 'marketplaces:inventory-automation';
    protected $description = 'Dispatch due marketplace inventory synchronization and monitoring jobs';

    public function handle(MarketplaceInventoryAutomationService $automation): int
    {
        $count = $automation->scheduleDue();
        $this->info("Dispatched {$count} marketplace inventory automation job(s).");

        return self::SUCCESS;
    }
}
