<?php

namespace App\Jobs;

use App\Models\MarketplaceConnection;
use App\Services\MarketplaceReadSyncService;
use DateTimeImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

class RunMarketplaceReadSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 180;

    public function __construct(
        public readonly int $connectionId,
        public readonly string $resource,
        public readonly ?string $from = null,
        public readonly ?string $to = null,
    ) {}

    public function backoff(): array { return [30, 120, 300]; }

    public function handle(MarketplaceReadSyncService $sync): void
    {
        $connection = MarketplaceConnection::query()->findOrFail($this->connectionId);
        $run = $sync->sync(
            $connection,
            $this->resource,
            $this->from ? new DateTimeImmutable($this->from) : null,
            $this->to ? new DateTimeImmutable($this->to) : null,
        );

        if ($run->status === 'FAILED') {
            throw new RuntimeException($run->error_message ?: 'Marketplace read synchronization failed.');
        }
    }
}
