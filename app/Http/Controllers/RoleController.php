<?php
namespace App\Http\Controllers;

use App\Models\{Permission, Role};
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Super Admin only (route uses pseudo-permission roles.manage, which only Super Admin passes via Gate::before). */
class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::with('permissions')->withCount('users')->orderByRaw("slug = 'super-admin' desc")->orderBy('name')->get();
        return view('roles.index', [
            'roles' => $roles,
            'permissions' => Permission::orderBy('module')->orderBy('name')->get()->groupBy('module'),
            'matrix' => $roles->mapWithKeys(fn ($r) => [$r->id => $r->permissions->pluck('id')->all()])->all(),
        ]);
    }

    public function update(Request $request)
    {
        $request->validate(['perm' => 'nullable|array', 'perm.*' => 'array', 'perm.*.*' => 'exists:permissions,id']);
        DB::transaction(function () use ($request) {
            foreach (Role::where('slug', '!=', 'super-admin')->get() as $role) {
                $new = array_map('intval', $request->input("perm.{$role->id}", []));
                $before = $role->permissions()->pluck('permissions.name')->sort()->values()->all();
                $role->permissions()->sync($new);
                $after = $role->permissions()->pluck('permissions.name')->sort()->values()->all();
                if ($before !== $after) {
                    ActivityLogger::log('role.permissions_changed', $role,
                        ['removed' => array_values(array_diff($before, $after))], ['added' => array_values(array_diff($after, $before))]);
                }
            }
        });
        return back()->with('success', 'Permissions saved.');
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:60|unique:roles,name']);
        $slug = Str::slug($data['name']);
        if (Role::where('slug', $slug)->exists()) return back()->withInput()->with('error', 'A role with a similar name exists.');
        $role = Role::create(['name' => $data['name'], 'slug' => $slug, 'is_system' => false]);
        ActivityLogger::log('role.created', $role, null, ['name' => $role->name]);
        return back()->with('success', 'Role added. Tick its permissions below and save.');
    }

    public function destroy(Role $role)
    {
        if ($role->is_system) return back()->with('error', 'System roles cannot be deleted.');
        if ($role->users()->exists()) return back()->with('error', 'Role is assigned to users.');
        $role->permissions()->detach();
        $role->delete();
        ActivityLogger::log('role.deleted', $role, ['name' => $role->name]);
        return back()->with('success', 'Role deleted.');
    }
}
