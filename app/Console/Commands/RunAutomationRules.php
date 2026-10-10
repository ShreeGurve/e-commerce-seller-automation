<?php
namespace App\Console\Commands;
use App\Services\AutomationService;
use Illuminate\Console\Command;
class RunAutomationRules extends Command {
 protected $signature='automation:run';
 protected $description='Evaluate enabled SHREE GURVE automation rules and create internal alerts';
 public function handle(AutomationService $service): int {
  $result=$service->run();
  $this->info("Evaluated {$result['rules']} active rule(s); {$result['matched']} match(es); {$result['created']} new alert(s).");
  return self::SUCCESS;
 }
}
