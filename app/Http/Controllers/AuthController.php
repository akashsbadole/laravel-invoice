<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function create(): Response|RedirectResponse
    {
        if (Auth::check()) {
            return to_route('dashboard');
        }

        return Inertia::render('auth/login', [
            'status' => session('status'),
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => __('These credentials do not match our records.'),
            ])->onlyInput('email');
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => __('Your account has been deactivated. Please contact an administrator.'),
            ])->onlyInput('email');
        }

        if ($user->two_factor_secret && $user->two_factor_confirmed_at) {
            Auth::guard('web')->logout();
            $request->session()->put('two_factor.user_id', $user->id);
            $request->session()->put('two_factor.remember', $request->boolean('remember'));

            return to_route('two-factor.login');
        }

        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        // A platform super admin has no tenant, so send them to the admin
        // panel rather than a tenant dashboard they cannot load.
        if ($user->isSuperAdmin()) {
            return redirect()->intended(route('admin.dashboard'));
        }

        return redirect()->intended(route('dashboard'));
    }

    public function twoFactorChallenge(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has('two_factor.user_id')) {
            return to_route('login');
        }

        return Inertia::render('auth/two-factor-challenge');
    }

    public function verifyTwoFactor(Request $request): RedirectResponse
    {
        $userId = $request->session()->get('two_factor.user_id');

        if (! $userId) {
            return to_route('login');
        }

        /** @var User $user */
        $user = User::query()->findOrFail($userId);

        $request->validate([
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        $valid = false;

        if ($code = $request->input('code')) {
            $secret = decrypt($user->two_factor_secret);
            $google2fa = new \PragmaRX\Google2FA\Google2FA();
            $valid = $google2fa->verifyKey($secret, $code);
        } elseif ($recoveryCode = $request->input('recovery_code')) {
            $codes = $user->two_factor_recovery_codes ? json_decode(decrypt($user->two_factor_recovery_codes), true) : [];
            if (($key = array_search(trim($recoveryCode), $codes, true)) !== false) {
                unset($codes[$key]);
                $user->forceFill([
                    'two_factor_recovery_codes' => encrypt(json_encode(array_values($codes))),
                ])->save();
                $valid = true;
            }
        }

        if (! $valid) {
            return back()->withErrors([
                'code' => __('The provided two-factor authentication code was invalid.'),
            ]);
        }

        $remember = $request->session()->get('two_factor.remember', false);
        $request->session()->forget(['two_factor.user_id', 'two_factor.remember']);

        Auth::login($user, $remember);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        if ($user->isSuperAdmin()) {
            return redirect()->intended(route('admin.dashboard'));
        }

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('login');
    }

    public function forgot(): Response|RedirectResponse
    {
        if (Auth::check()) {
            return to_route('dashboard');
        }

        return Inertia::render('auth/forgot-password', [
            'status' => session('status'),
        ]);
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? back()->with('status', __($status))
            : back()->withErrors(['email' => __($status)]);
    }

    public function reset(string $token): Response|RedirectResponse
    {
        if (Auth::check()) {
            return to_route('dashboard');
        }

        return Inertia::render('auth/reset-password', [
            'token' => $token,
            'email' => request()->query('email'),
            'status' => session('status'),
        ]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? to_route('login')->with('status', __($status))
            : back()->withErrors(['email' => __($status)]);
    }
}
