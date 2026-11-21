<?php

namespace App\Jobs;

use App\Services\MarketplaceInventoryAutomationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

class RunMarketplaceInventoryAutomation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 240;

    public function __construct(public readonly int $connectionId, public readonly int $settingId) {}

    public function backoff(): array
    {
        return [60, 180, 600];
    }

    public function handle(MarketplaceInventoryAutomationService $automation): void
    {
        $run = $automation->run($this->connectionId, $this->settingId);
        if ($run->status === 'FAILED') {
            throw new RuntimeException($run->message ?: 'Marketplace inventory automation failed.');
        }
    }
}
