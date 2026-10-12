<?php
namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function edit(Request $request) { return view('profile.edit', ['user' => $request->user()->load('roles')]); }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => 'nullable|string|max:20',
        ]);
        $user->fill($data);
        $dirty = $user->getDirty(); $old = array_intersect_key($user->getOriginal(), $dirty);
        $user->save();
        if ($dirty) ActivityLogger::log('profile.updated', $user, $old, $dirty);
        return back()->with('success', 'Profile updated.');
    }

    public function password(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required|current_password',
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers(), 'different:current_password'],
        ]);
        $request->user()->update(['password' => $data['password']]); // 'hashed' cast
        ActivityLogger::log('profile.password_changed', $request->user());
        return back()->with('success', 'Password changed.');
    }
}
