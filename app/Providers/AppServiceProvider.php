<?php
namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Old WAMP MySQL/MariaDB defaults to MyISAM (1000-byte key limit, NO foreign keys / transactions).
        // Force InnoDB so FKs + DB transactions work, and keep indexed strings safe on older servers.
        config(['database.connections.mysql.engine' => 'InnoDB']);
        Schema::defaultStringLength(191);

        Paginator::useBootstrapFive();

        // Permission names like "products.view" work with @can, can: middleware and Gate::allows.
        Gate::before(function ($user, string $ability) {
            if ($user->isSuperAdmin()) {
                return true;
            }
            return str_contains($ability, '.') ? ($user->hasPermission($ability) ?: null) : null;
        });
    }
}
