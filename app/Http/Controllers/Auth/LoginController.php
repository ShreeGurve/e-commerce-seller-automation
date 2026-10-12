<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function show() { return view('auth.login'); }

    public function store(Request $request)
    {
        $cred = $request->validate(['email' => 'required|email', 'password' => 'required|string']);

        if (Auth::attempt($cred + ['is_active' => true], $request->boolean('remember'))) {
            $request->session()->regenerate();
            $request->user()->forceFill(['last_login_at' => now()])->save();
            ActivityLogger::log('auth.login');
            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors(['email' => 'Invalid credentials or inactive account.'])->onlyInput('email');
    }

    public function destroy(Request $request)
    {
        ActivityLogger::log('auth.logout');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
