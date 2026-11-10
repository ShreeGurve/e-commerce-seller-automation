<?php

namespace App\Console\Commands;

use App\Models\AutomationTask;
use App\Models\AutomationTaskReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendAutomationTaskReminders extends Command
{
    protected $signature = 'automation:tasks-remind {--hours=24} {--limit=500}';
    protected $description = 'Create in-app reminder records for open tasks due soon or overdue';

    public function handle(): int
    {
        $hours = max(1, min(168, (int)$this->option('hours')));
        $limit = max(1, min(2000, (int)$this->option('limit')));
        $created = 0;
        AutomationTask::where('status','OPEN')->whereNotNull('due_at')->where('due_at','<=',now()->addHours($hours))
            ->orderBy('due_at')->limit($limit)->get()->each(function ($task) use (&$created) {
                DB::transaction(function () use ($task, &$created) {
                    $exists = AutomationTaskReminder::where('task_id',$task->id)->where('reminder_type','due')->exists();
                    if ($exists) return;
                    AutomationTaskReminder::create(['task_id'=>$task->id,'user_id'=>$task->assigned_to,'reminder_type'=>'due','reminded_at'=>now()]);
                    $created++;
                });
            });
        $this->info("Created {$created} in-app reminder(s).");
        return self::SUCCESS;
    }
}
