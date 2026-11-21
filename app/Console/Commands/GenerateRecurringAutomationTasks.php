<?php

namespace App\Console\Commands;

use App\Models\AutomationRecurringTask;
use App\Models\AutomationTask;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateRecurringAutomationTasks extends Command
{
    protected $signature = 'automation:tasks-generate-recurring {--limit=200}';

    protected $description = 'Generate due occurrences from active internal recurring task schedules';

    public function handle(): int
    {
        $limit = max(1, min(1000, (int) $this->option('limit')));
        $count = 0;
        AutomationRecurringTask::where('is_active', true)->where('next_due_at', '<=', now())->orderBy('next_due_at')->limit($limit)->get()->each(function ($template) use (&$count) {
            DB::transaction(function () use ($template, &$count) {
                $template = AutomationRecurringTask::whereKey($template->id)->lockForUpdate()->first();
                if (! $template || ! $template->is_active || $template->next_due_at->isFuture()) {
                    return;
                }
                $due = $template->next_due_at->copy();
                AutomationTask::create([
                    'business_id' => $template->business_id, 'title' => $template->title, 'description' => $template->description,
                    'assigned_to' => $template->assigned_to, 'created_by' => $template->created_by, 'priority' => $template->priority,
                    'status' => 'OPEN', 'due_at' => $due, 'recurring_template_id' => $template->id,
                ]);
                $next = $due->copy();
                match ($template->frequency) {
                    'daily' => $next->addDays($template->interval_value),
                    'weekly' => $next->addWeeks($template->interval_value),
                    'monthly' => $next->addMonthsNoOverflow($template->interval_value),
                    default => $next->addWeek(),
                };
                $template->update(['next_due_at' => $next]);
                $count++;
            });
        });
        $this->info("Generated {$count} recurring task(s).");

        return self::SUCCESS;
    }
}
