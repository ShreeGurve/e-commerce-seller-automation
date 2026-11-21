<?php

namespace App\Console\Commands;

use App\Services\MarketplaceWriteExecutionMonitoringService;
use Illuminate\Console\Command;

class MonitorMarketplaceWriteExecutions extends Command
{
    protected $signature = 'marketplace-writes:monitor {--business= : Limit scan to one business ID}';

    protected $description = 'Detect stale, failed, and uncertain marketplace write executions without retrying them';

    public function handle(MarketplaceWriteExecutionMonitoringService $service): int
    {
        $business = $this->option('business');
        $result = $service->scan($business !== null ? (int) $business : null);
        $this->info("Checked {$result['detected']} attempts; created {$result['created']} incidents (stale threshold: {$result['stale_minutes']} minutes).");

        return self::SUCCESS;
    }
}
