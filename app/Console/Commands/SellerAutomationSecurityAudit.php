<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SellerAutomationSecurityAudit extends Command
{
    protected $signature = 'seller-automation:security-audit {--skip-database : Skip database checks} {--strict : Treat optional recommendations as failures}';
    protected $description = 'Run a read-only security and permission readiness audit';

    public function handle(): int
    {
        $checks = [];
        $add = function ($area, $name, $ok, $detail, $optional = false) use (&$checks) {
            $checks[] = compact('area', 'name', 'ok', 'detail', 'optional');
        };

        $add('Environment', 'APP_DEBUG disabled', !config('app.debug'), 'Set APP_DEBUG=false in production.');
        $add('Environment', 'APP_KEY configured', filled(config('app.key')), 'Configure APP_KEY using Laravel key-generation procedure.');
        $add('Session', 'Secure cookie for HTTPS production', (bool)config('session.secure') || !app()->environment('production'), 'Set SESSION_SECURE_COOKIE=true for HTTPS production.', true);
        $add('Storage', 'Private storage directory exists', is_dir(storage_path('app/private')), 'Create storage/app/private; keep confidential documents outside public web root.', true);

        foreach (Route::getRoutes() as $route) {
            $uri = strtolower($route->uri());
            if (preg_match('/(invoice-documents|supplier-statements|supplier-payments|credit-notes|warehouse|procurement|automation|governance|operational-insights)/', $uri)) {
                $middleware = $route->gatherMiddleware();
                $hasAuth = collect($middleware)->contains(fn($m) => $m === 'auth' || str_starts_with($m, 'auth:'));
                $add('Route protection', $route->getName() ?: $route->uri(), $hasAuth, 'Review route group/middleware inheritance if flagged.');
            }
        }

        if (!$this->option('skip-database')) {
            try {
                DB::connection()->getPdo();
                foreach (['roles','permissions','model_has_roles','model_has_permissions','role_has_permissions'] as $table) {
                    $add('Permission schema', $table, Schema::hasTable($table), 'If using Spatie, verify its migrations. Custom schemas need manual review.');
                }
                if (collect($checks)->filter(fn($c) => $c['area']==='Permission schema' && $c['ok'])->count() === 5) {
                    $add('Permission data', 'At least one role exists', DB::table('roles')->count() > 0, 'Create roles using your approved role-management workflow.');
                    $add('Permission data', 'At least one permission exists', DB::table('permissions')->count() > 0, 'Seed/configure the permission catalogue.');
                }
            } catch (Throwable $e) {
                $add('Database', config('database.default'), false, 'Could not inspect permission schema: '.$e->getMessage());
            }
        }

        $this->table(['Area','Check','Result','Recommendation'], array_map(function($c) {
            $status = $c['ok'] ? '<fg=green>PASS</>' : (($c['optional'] && !$this->option('strict')) ? '<fg=yellow>REVIEW</>' : '<fg=red>FAIL</>');
            return [$c['area'],$c['name'],$status,$c['detail']];
        }, $checks));

        $failures = collect($checks)->filter(fn($c) => !$c['ok'] && (!$c['optional'] || $this->option('strict')))->count();
        $reviews = collect($checks)->filter(fn($c) => !$c['ok'] && $c['optional'] && !$this->option('strict'))->count();
        $this->info('Security audit: '.(count($checks)-$failures-$reviews).' passed, '.$reviews.' review, '.$failures.' failed.');
        $this->warn('Read-only audit: no roles, permissions, files, routes or configuration are modified.');
        return $failures ? self::FAILURE : self::SUCCESS;
    }
}
