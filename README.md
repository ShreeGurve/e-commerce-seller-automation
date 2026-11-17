# Seller Automation — Batch 75–77

This patch adds employee task productivity indicators, task dependency planning, and advanced workflow analytics.

## Included packages

### Package 75 — Employee Productivity Insights
- Per-employee open and overdue task counts.
- Tasks completed and created in the last 30 days.
- Completion ratio (completed in period / created in period).
- Average completion duration for tasks completed in the period.
- These are operational indicators, not an employee rating or disciplinary score.

### Package 76 — Task Dependency Management
- Define prerequisite task → dependent task relationships.
- Reject self-dependencies and direct/indirect cycles.
- Prevent duplicate dependency pairs.
- Show whether a prerequisite is complete or still blocking the dependent task.
- Removing a dependency does not change either task's status.

**Important limitation:** Dependencies are currently a planning/visibility control. This patch does not intercept the existing task-completion endpoint, so it does not technically prevent a user from completing a dependent task before its prerequisite. Integrate a dependency check into the existing completion action if hard enforcement is required.

### Package 77 — Advanced Workflow Analytics
- Selectable 7, 14, 30, 60, or 90-day reporting period.
- Created/completed task totals, open and overdue backlog.
- Completion ratio, average completion time, on-time completions.
- Daily task creation and completion trend table.

## Installation

Prerequisite: Packages 69–71 and 72–74 must be installed, and their `automation_tasks` table and `escalated_at` column must exist.

1. Back up the project.
2. Extract this ZIP into the Laravel project root, preserving paths.
3. Add the routes from `routes/route-snippets.php` inside the existing authenticated route group in `routes/web.php`. Place them before broad/dynamic automation routes.
4. Run:

```bash
php artisan optimize:clear
php artisan migrate
php artisan route:list --name=automation.insights
```

Expected route names:
- `automation.insights.productivity`
- `automation.insights.dependencies`
- `automation.insights.analytics`
- `automation.insights.dependencies.store`
- `automation.insights.dependencies.destroy`

## Permissions and tenant scope

- Read-only pages use the existing `can:reports.view` middleware.
- Dependency create/delete actions use `can:purchases.edit`.
- Queries are scoped to the authenticated user's `business_id`, except for Super Admin users.
- Uses the existing `users` and `automation_tasks` tables. No new Composer dependency is required.

## Validation

PHP syntax and ZIP integrity are checked during packaging. No database migration, browser, route-registration, or end-to-end test has been run against the user's local database. Back up before installing and verify route names after merging the route snippet.
