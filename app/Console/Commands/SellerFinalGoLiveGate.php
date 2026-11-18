<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Exception\CommandNotFoundException;

class SellerFinalGoLiveGate extends Command
{
    protected $signature = 'seller-automation:final-go-live-gate';
    protected $description = 'Run the read-only Seller Automation release checks and summarize go-live blockers';

    public function handle(): int
    {
        $this->info('Seller Automation — Final Go-Live Gate');
        $commands = [
            'seller-automation:environment-audit',
            'seller-automation:schema-audit',
            'seller-automation:release-readiness',
            'seller-automation:marketplace-readiness',
            'seller-automation:test-order-audit',
            'seller-automation:scheduler-queue-audit',
        ];
        $failed = 0;

        foreach ($commands as $command) {
            $this->newLine();
            $this->line('--- '.$command.' ---');
            try {
                // The Laravel Console Kernel does not expose Artisan::has() in
                // supported framework versions. Attempt the call and handle an
                // unregistered command using Symfony's command exception.
                $exit = Artisan::call($command);
                $this->output->write(Artisan::output());
                if ($exit !== self::SUCCESS) {
                    $this->error("FAILED: {$command} (exit {$exit})");
                    $failed++;
                }
            } catch (CommandNotFoundException $e) {
                $this->warn("NOT RUN: {$command} is not registered. Install the corresponding earlier batch and clear caches.");
                $failed++;
            } catch (\Throwable $e) {
                $this->error("FAILED: {$command} — {$e->getMessage()}");
                $failed++;
            }
        }

        $this->newLine();
        $this->line('Human sign-off still required: marketplace seller/API approvals and sandbox tests; confirmation that TEST orders are excluded from all financial outputs; backup restore drill; production scheduler/worker monitoring; full automated and manual regression tests; and rollback plan.');
        if ($failed > 0) {
            $this->error("Final gate: NOT READY — {$failed} command(s) failed or were not registered.");
            return self::FAILURE;
        }
        $this->warn('Final gate: TECHNICAL CHECKS COMPLETED, NOT A PRODUCTION CERTIFICATION. Complete all human sign-off items before enabling live marketplace writes.');
        return self::SUCCESS;
    }
}
