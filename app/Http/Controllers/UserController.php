<?php
namespace App\Http\Controllers;

use App\Models\{Role, User};
use App\Services\{ActivityLogger, SettingsService};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::with('roles')
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%")))
            ->orderBy('name')->paginate(20)->withQueryString();
        return view('users.index', compact('users'));
    }

    public function create() { return view('users.create', ['user' => new User(['is_active' => true]), 'roles' => $this->assignableRoles(), 'selected' => []]); }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $user = User::create([
            'business_id' => SettingsService::businessId(), 'name' => $data['name'], 'email' => $data['email'],
            'phone' => $data['phone'] ?? null, 'password' => $data['password'], 'is_active' => $request->boolean('is_active'),
        ]);
        $user->roles()->sync($this->finalRoleIds($request, null));
        ActivityLogger::log('user.created', $user, null, ['email' => $user->email, 'roles' => $user->roles()->pluck('slug')->all()]);
        return redirect()->route('users.index')->with('success', 'User created.');
    }

    public function edit(User $user)
    {
        return view('users.edit', ['user' => $user, 'roles' => $this->assignableRoles(), 'selected' => $user->roles()->pluck('roles.id')->all()]);
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, $user);
        $active = $request->boolean('is_active');
        $roleIds = $this->finalRoleIds($request, $user);

        if ($user->id === auth()->id() && ! $active) {
            return back()->withInput()->with('error', 'You cannot deactivate your own account.');
        }
        $superId = Role::where('slug', 'super-admin')->value('id');
        if ($user->isSuperAdmin() && $user->is_active && (! $active || ! in_array($superId, $roleIds)) && $this->activeSuperAdmins($user->id) === 0) {
            return back()->withInput()->with('error', 'At least one active Super Admin must remain.');
        }

        $oldRoles = $user->roles()->pluck('slug')->all();
        $fill = ['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null, 'is_active' => $active];
        if (! empty($data['password'])) $fill['password'] = $data['password'];
        $user->fill($fill);
        $dirty = collect($user->getDirty())->except('password')->all();
        $old = array_intersect_key($user->getOriginal(), $dirty);
        $pwChanged = $user->isDirty('password');
        $user->save();
        $user->roles()->sync($roleIds);
        $newRoles = $user->roles()->pluck('slug')->all();
        if ($dirty || $pwChanged || $oldRoles != $newRoles) {
            ActivityLogger::log('user.updated', $user, $old + ['roles' => $oldRoles], $dirty + ['roles' => $newRoles] + ($pwChanged ? ['password' => 'changed'] : []));
        }
        return redirect()->route('users.index')->with('success', 'User updated.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) return back()->with('error', 'You cannot delete your own account.');
        if ($user->isSuperAdmin() && $user->is_active && $this->activeSuperAdmins($user->id) === 0) {
            return back()->with('error', 'At least one active Super Admin must remain.');
        }
        $user->delete();
        ActivityLogger::log('user.deleted', $user, ['email' => $user->email]);
        return redirect()->route('users.index')->with('success', 'User deleted. (Tip: deactivate instead to keep the email reserved.)');
    }

    private function validated(Request $request, ?User $u = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($u?->id)],
            'phone' => 'nullable|string|max:20',
            'password' => [$u ? 'nullable' : 'required', 'confirmed', Password::min(8)->letters()->numbers()],
            'roles' => 'nullable|array', 'roles.*' => 'exists:roles,id',
        ]);
    }

    /** Only a Super Admin may hand out the Super Admin role (blocks privilege escalation). */
    private function assignableRoles()
    {
        return Role::orderBy('name')->get()->when(! auth()->user()->isSuperAdmin(), fn ($c) => $c->reject(fn ($r) => $r->slug === 'super-admin'))->values();
    }

    private function finalRoleIds(Request $request, ?User $target): array
    {
        $ids = array_map('intval', $request->input('roles', []));
        if (! auth()->user()->isSuperAdmin()) {
            $superId = Role::where('slug', 'super-admin')->value('id');
            $ids = array_values(array_diff($ids, [$superId]));
            if ($target && $target->isSuperAdmin()) $ids[] = $superId; // keep what they already have
        }
        return array_values(array_unique($ids));
    }

    private function activeSuperAdmins(int $excludeId): int
    {
        return User::where('is_active', true)->where('id', '!=', $excludeId)->whereHas('roles', fn ($q) => $q->where('slug', 'super-admin'))->count();
    }
}
