<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SellerMarketplaceReadinessAudit extends Command
{
    protected $signature = 'seller-automation:marketplace-readiness';

    protected $description = 'Read-only presence audit for Amazon and Flipkart integration configuration';

    public function handle(): int
    {
        $this->info('Seller Automation — Marketplace Integration Readiness (read-only)');
        $services = (array) config('services', []);
        $checks = 0;
        $warnings = 0;

        foreach (['amazon', 'flipkart'] as $marketplace) {
            $matches = [];
            $this->collectMarketplaceSettings($services, $marketplace, '', $matches);
            $configured = count($matches) > 0;
            $checks++;
            if (! $configured) {
                $warnings++;
            }
            $this->line(($configured ? '<fg=green>CONFIG FOUND</>' : '<fg=yellow>NOT DETECTED</>')." {$marketplace}: ".($configured ? count($matches).' related configuration value(s) present' : 'No matching services configuration detected'));
            if ($configured) {
                foreach ($matches as $key => $present) {
                    $this->line('  - '.$key.': '.($present ? '<fg=green>present</>' : '<fg=yellow>empty</>'));
                }
            }
        }

        $this->newLine();
        $this->comment('Configuration presence is not proof of valid credentials, seller approval, API scopes or successful API calls. No secrets or values are displayed; no marketplace requests or writes are made.');
        $this->info("Marketplaces checked: {$checks} | Not detected: {$warnings}");

        return self::SUCCESS;
    }

    private function collectMarketplaceSettings(array $node, string $marketplace, string $prefix, array &$matches): void
    {
        foreach ($node as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            $normalized = strtolower($path);
            if (str_contains($normalized, $marketplace)) {
                if (is_array($value)) {
                    $this->collectMarketplaceSettings($value, $marketplace, $path, $matches);
                } else {
                    $matches[$path] = $value !== null && $value !== '';
                }
            } elseif (is_array($value)) {
                $this->collectMarketplaceSettings($value, $marketplace, $path, $matches);
            }
        }
    }
}
