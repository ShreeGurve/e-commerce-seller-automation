<?php

namespace App\Console\Commands;

use App\Services\MarketplaceWriteEscalationService;
use Illuminate\Console\Command;

class ProcessMarketplaceWriteEscalations extends Command
{
    protected $signature = 'marketplace-writes:escalate';

    protected $description = 'Apply enabled marketplace write incident SLA escalation rules';

    public function handle(MarketplaceWriteEscalationService $service): int
    {
        $count = $service->process();
        $this->info("Escalations applied: {$count}");

        return self::SUCCESS;
    }
}
