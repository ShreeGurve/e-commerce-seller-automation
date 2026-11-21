<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SellerSchedulerQueueAudit extends Command
{
    protected $signature = 'seller-automation:scheduler-queue-audit';

    protected $description = 'Read-only audit of scheduler registration and queue configuration';

    public function handle(): int
    {
        $this->info('Seller Automation — Scheduler & Queue Audit (read-only)');
        $queue = (string) config('queue.default', '');
        $this->line('Queue connection: '.($queue !== '' ? $queue : 'NOT CONFIGURED'));
        $this->line('Queue driver is asynchronous: '.($queue !== '' && $queue !== 'sync' ? '<fg=green>YES</>' : '<fg=yellow>NO / SYNC</>'));

        foreach (['jobs', 'failed_jobs'] as $table) {
            $this->line("Table {$table}: ".(Schema::hasTable($table) ? '<fg=green>FOUND</>' : '<fg=yellow>NOT FOUND</>'));
        }

        try {
            $schedule = app(Schedule::class);
            $events = $schedule->events();
            $this->line('Registered scheduled events: '.count($events));
            foreach ($events as $event) {
                $description = $event->description ?? $event->command ?? $event->getSummaryForDisplay();
                $expression = method_exists($event, 'expression') ? $event->expression : 'schedule expression unavailable';
                $this->line('  - '.$description.' | '.$expression);
            }
            if (count($events) === 0) {
                $this->warn('No scheduled events were registered in the loaded application schedule.');
            }
        } catch (Throwable $e) {
            $this->warn('Could not inspect registered schedule: '.$e->getMessage());
        }

        $this->newLine();
        $this->comment('A configured queue driver and registered schedule do not prove that workers or the scheduler are running on the host. Verify the OS scheduler, worker supervisor, failed jobs and logs after deployment.');

        return self::SUCCESS;
    }
}
