<?php

namespace App\Console\Commands;

use App\Services\MarketplaceWriteComplianceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RunMarketplaceWriteCompliance extends Command
{
    protected $signature = 'marketplace-writes:compliance-scan';

    protected $description = 'Scan marketplace write evidence retention and governance compliance for every business.';

    public function handle(MarketplaceWriteComplianceService $service): int
    {
        $count = 0;
        DB::table('businesses')->select('id')->orderBy('id')->chunkById(100, function ($businesses) use ($service, &$count) {
            foreach ($businesses as $business) {
                $service->runForBusiness((int) $business->id);
                $count++;
            }
        });

        $this->info("Completed compliance scans for {$count} business(es).");

        return self::SUCCESS;
    }
}
