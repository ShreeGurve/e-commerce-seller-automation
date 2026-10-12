<?php
namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            'products' => ['view', 'create', 'edit', 'delete'],
            'categories' => ['manage'],
            'purchases' => ['view', 'create', 'edit'],
            'inventory' => ['view', 'manage'],
            'orders' => ['view', 'edit'],
            'returns' => ['view', 'manage'],
            'claims' => ['view', 'create', 'manage'],
            'advertising' => ['view', 'manage'],
            'finance' => ['view', 'manage', 'close_period'],
            'listings' => ['view', 'manage'],
            'reports' => ['view', 'export'],
            'automation' => ['view', 'manage'],
            'approvals' => ['view', 'decide'],
            'settings' => ['manage'],
            'users' => ['manage'],
        ];

        $all = [];
        foreach ($modules as $module => $actions) {
            foreach ($actions as $a) {
                $p = Permission::firstOrCreate(['name' => "$module.$a"], ['module' => $module]);
                $all[$p->name] = $p->id;
            }
        }

        $pick = fn (array $prefixes) => collect($all)->filter(
            fn ($id, $name) => collect($prefixes)->contains(fn ($pre) => str_starts_with($name, $pre))
        )->values()->all();

        $roles = [
            'super-admin' => ['Super Admin', array_values($all)],
            'admin' => ['Admin', array_values(array_diff_key($all, ['finance.close_period' => 1]))],
            'manager' => ['Manager', $pick(['products.', 'categories.', 'orders.', 'returns.', 'claims.', 'inventory.', 'purchases.', 'reports.', 'approvals.view'])],
            'accountant' => ['Accountant', $pick(['finance.', 'reports.', 'orders.view', 'purchases.view'])],
            'inventory-manager' => ['Inventory Manager', $pick(['inventory.', 'purchases.', 'products.view', 'returns.'])],
            'marketing-manager' => ['Marketing Manager', $pick(['advertising.', 'listings.', 'products.view', 'reports.view'])],
            'viewer' => ['Viewer', collect($all)->filter(fn ($id, $n) => str_ends_with($n, '.view'))->values()->all()],
        ];

        foreach ($roles as $slug => [$name, $permIds]) {
            $role = Role::firstOrCreate(['slug' => $slug], ['name' => $name, 'is_system' => true]);
            $role->permissions()->sync($permIds);
        }
    }
}
