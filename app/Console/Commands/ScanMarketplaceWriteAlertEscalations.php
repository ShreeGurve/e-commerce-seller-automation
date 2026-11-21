<?php

namespace App\Console\Commands;

use App\Services\MarketplaceWriteAlertEscalationService;
use Illuminate\Console\Command;

class ScanMarketplaceWriteAlertEscalations extends Command
{
    protected $signature = 'marketplace-writes:alerts-escalate';

    protected $description = 'Escalate eligible unread marketplace write governance alerts';

    public function handle(MarketplaceWriteAlertEscalationService $service): int
    {
        $result = $service->scan();
        $this->info("Checked {$result['checked']} alerts; created {$result['created']} escalations.");

        return self::SUCCESS;
    }
}
