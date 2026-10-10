SHREE GURVE Seller Automation — Update 14 / Phase 8
Features: dashboard KPIs, filtered reports and CSV export, notification inbox, configurable internal automation rules, idempotent alert log, hourly scheduled evaluation.

Install:
1. Back up your application folder and database.
2. Extract/merge this ZIP over your existing Laravel project; preserve .env.
3. Run `php artisan migrate`.
4. Run `php artisan optimize:clear`.
5. Open Reports, Automation, Notifications in the sidebar.
6. Create automation rules and click Run now to test.
7. For automatic hourly runs, ensure the host runs Laravel's scheduler every minute. On Windows/WAMP, configure Windows Task Scheduler to run `php artisan schedule:run` every minute from the project directory.

Triggers: low stock, claim deadline approaching, estimated order-line profit below threshold, and high ACOS where campaign-level ad data exists.
Safety: rules generate internal notifications only; they do not submit claims or modify marketplace prices, listings, campaigns or budgets.
Inventory report is a current snapshot; its date filter does not change inventory state.
Validation performed: ZIP integrity and PHP syntax checks. Runtime/database verification must be done in your local environment with your .env, Composer dependencies and MySQL.
