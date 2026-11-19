<?php

namespace App\Console\Commands;

use App\Services\DynamicPricingService;
use Illuminate\Console\Command;

class RunDynamicPricing extends Command
{
    protected $signature = 'marketplaces:dynamic-pricing {--business=} {--marketplace=}';
    protected $description = 'Generate approval-gated marketplace price recommendations from enabled pricing rules';

    public function handle(DynamicPricingService $service): int
    {
        $run = $service->run($this->option('business') ? (int) $this->option('business') : null, $this->option('marketplace') ? (int) $this->option('marketplace') : null);
        $this->line($run->summary ?: 'Dynamic pricing run finished.');
        return $run->status === 'COMPLETED' ? self::SUCCESS : self::FAILURE;
    }
}
