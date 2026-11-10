# Seller Automation — Batch 66–68

This patch adds three workflow improvements using the existing `business_automation_events` work queue and the existing custom permission system. It does not add a new task table or install Spatie.

## Packages

### Package 66 — Automated Task Generation / Follow-up Queue
- Adds a manual follow-up task form that records tasks as `manual_followup` events in the existing business automation event table.
- Supports assignee, due date and priority.
- Provides assigned, unassigned and “assigned to me” filters.
- The task creation action is explicit; no background tasks are created automatically.

### Package 67 — Smart Alert Prioritization
- Calculates an advisory attention score (0–100) from event severity, stored priority, deadline proximity/overdue status and assignment status.
- Sorts open alerts by score.
- Does not overwrite stored alert priority or execute any action.

### Package 68 — Business Workflow Monitor
- Shows open, overdue, unassigned, due-today and recently resolved counts.
- Lists open work items ordered by deadline.
- Read-only monitoring view.

## Files

- `app/Http/Controllers/AutomationOperationsController.php`
- `resources/views/automation/tasks.blade.php`
- `resources/views/automation/priorities.blade.php`
- `resources/views/automation/workflow-monitor.blade.php`
- `routes/route-snippets.php`

## Requirements

The project must already have these existing migrations applied:

- `2026_11_05_000042_create_executive_automation_tables.php`
- `2026_11_07_000048_add_work_queue_fields_to_business_automation_events.php`

The latter adds `assigned_to`, `assigned_at`, `due_at` and `priority`. No new migration or Composer dependency is included in this batch.

## Installation

1. Back up your project.
2. Extract the ZIP into the Laravel project root, preserving the `app/` and `resources/` paths.
3. Open `routes/web.php` and merge all four definitions from `routes/route-snippets.php` inside the existing `Route::middleware('auth')->group(...)`. Do not replace the complete route file. Keep these routes before any broad/dynamic automation route declarations.
4. Run:

```bash
php artisan optimize:clear
php artisan route:clear
php artisan route:list --name=automation.tasks
php artisan route:list --name=automation.alert-priorities
php artisan route:list --name=automation.workflow-monitor
```

## Permissions

- View pages: `reports.view`
- Create follow-up tasks: `purchases.edit`

These are the project's existing custom permission names.

## Validation and limitations

- PHP syntax was checked for the controller and route snippet.
- ZIP integrity was checked.
- Database, browser and end-to-end tests were not run in this environment (the project `vendor/` directory is not present).
- Business events are scoped to the signed-in user's business through their creator relationship; super-admins can view all events.
- The score is a transparent advisory heuristic, not an AI prediction or automatic priority change.
