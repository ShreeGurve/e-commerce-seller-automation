<?php

namespace App\Console\Commands;

use App\Services\MarketplaceWriteGovernanceAlertService;
use Illuminate\Console\Command;

class ScanMarketplaceWriteGovernanceAlerts extends Command
{
    protected $signature = 'marketplace-writes:alerts-scan';

    protected $description = 'Create deduplicated database notifications for marketplace write governance alerts';

    public function handle(MarketplaceWriteGovernanceAlertService $service): int
    {
        $result = $service->scan();
        $this->info("Checked {$result['checked']} signals; created {$result['created']} notifications.");

        return self::SUCCESS;
    }
}
