<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function loginForm(): View
    {
        return view('auth', ['mode' => 'login']);
    }

    public function registerForm(): View
    {
        return view('auth', ['mode' => 'register']);
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate(['login' => 'required|string|max:254', 'password' => 'required|string|max:128']);
        $identifier = strtolower(trim($data['login']));
        $field = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $key = 'login:'.hash('sha256', $identifier.'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['login' => 'Demasiados intentos. Espera un minuto.']);
        }
        if (! Auth::attempt([$field => $identifier, 'password' => $data['password']])) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['login' => 'El usuario o correo, o la contraseña no son correctos.']);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();

        return redirect()->intended(route($request->user()->is_admin ? 'management' : 'account'));
    }

    public function register(Request $request): RedirectResponse
    {
        $request->merge(['email' => strtolower((string) $request->input('email', ''))]);
        $data = $request->validate(['name' => 'required|string|min:2|max:100', 'username' => 'required|string|min:3|max:50|alpha_dash|unique:users,username', 'empresa' => 'nullable|string|max:160', 'email' => 'required|email|max:254|unique:users,email', 'password' => 'required|string|min:12|max:128|confirmed', 'consentimiento' => 'accepted']);
        $user = User::create(['name' => $data['name'], 'username' => strtolower($data['username']), 'empresa' => $data['empresa'] ?? null, 'email' => strtolower($data['email']), 'password' => $data['password']]);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('account');
    }

    public function account(Request $request): View|RedirectResponse
    {
        if ($request->user()->is_admin) {
            return redirect()->route('management');
        }

        return view('auth', ['mode' => 'account']);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->user()->update($request->validate(['name' => 'required|string|min:2|max:100', 'empresa' => 'nullable|string|max:160']));

        return back()->with('success', 'Perfil actualizado.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
