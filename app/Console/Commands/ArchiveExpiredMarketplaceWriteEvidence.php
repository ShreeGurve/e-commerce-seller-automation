<?php

namespace App\Console\Commands;

use App\Services\MarketplaceWriteRetentionService;
use Illuminate\Console\Command;

class ArchiveExpiredMarketplaceWriteEvidence extends Command
{
    protected $signature = 'marketplace-writes:evidence-archive-expired';

    protected $description = 'Archive expired marketplace write evidence while respecting active legal holds.';

    public function handle(MarketplaceWriteRetentionService $service): int
    {
        $count = $service->archiveExpired();
        $this->info("Archived {$count} expired evidence record(s). No evidence was physically deleted.");

        return self::SUCCESS;
    }
}
