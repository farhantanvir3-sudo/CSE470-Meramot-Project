<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    /**
     * Show Sign In Page (Matching AuthCard.tsx at commit 8e44218)
     */
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect('/');
        }
        return view('auth.login');
    }

    /**
     * Handle Sign In Submission
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'identifier' => 'required|string',
            'password' => 'required|string',
        ]);

        $identifier = $credentials['identifier'];
        $fieldType = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $user = User::where($fieldType, $identifier)->first();

        if ($user && Hash::check($credentials['password'], $user->password)) {
            Auth::login($user, $request->boolean('remember'));
            $request->session()->regenerate();
            return redirect()->intended('/')->with('success', 'Welcome back, ' . $user->name . '!');
        }

        return back()->withInput($request->only('identifier'))->withErrors([
            'identifier' => 'Invalid email/username or password credentials.',
        ]);
    }

    /**
     * Show Sign Up Page (Matching AuthCard.tsx at commit 8e44218)
     */
    public function showRegister()
    {
        if (Auth::check()) {
            return redirect('/');
        }
        return view('auth.register');
    }

    /**
     * Handle Sign Up Submission
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'nullable|string|max:50|unique:users,username',
            'email' => 'required|email|max:255|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'username' => $validated['username'] ?? strtolower(str_replace(' ', '', $validated['name'])) . rand(100, 999),
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'role' => 'customer',
            'is_active' => true,
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/')->with('success', 'Account created successfully! Welcome to Meramot.');
    }

    /**
     * Fast Demo Sign-In (For developer/reviewer testing)
     */
    public function demoLogin(Request $request)
    {
        $role = $request->get('role', 'customer');
        $email = match ($role) {
            'vendor' => 'sakib.tech@gmail.com',
            'admin' => 'mustahid000@gmail.com',
            'delivery' => 'delivery.admin.demo@meeramoot.test',
            default => 'customer@meramot.demo',
        };

        $user = User::where('email', $email)->first();
        if ($user) {
            Auth::login($user);
            $request->session()->regenerate();
            return redirect()->back()->with('success', 'Logged in as ' . ucfirst($role) . ' (' . $user->name . ')');
        }

        return redirect()->back()->with('error', 'Demo user not found.');
    }

    /**
     * Log out
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('success', 'You have been logged out.');
    }
}
