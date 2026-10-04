<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use PragmaRX\Google2FA\Google2FA;

class SecurityController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $isConfirmed = $user->two_factor_confirmed_at !== null;
        $hasSecret = ! empty($user->two_factor_secret);

        $qrCodeSvg = null;
        $secret = null;

        if ($hasSecret && ! $isConfirmed) {
            $google2fa = new Google2FA();
            try {
                $secret = decrypt($user->two_factor_secret);
                $url = $google2fa->getQRCodeUrl(
                    config('app.name', 'JewelryApp'),
                    $user->email,
                    $secret,
                );
                $renderer = new ImageRenderer(
                    new RendererStyle(200),
                    new SvgImageBackEnd()
                );
                $writer = new Writer($renderer);
                $qrCodeSvg = $writer->writeString($url);
            } catch (\Throwable $e) {
                $qrCodeSvg = null;
            }
        }

        return Inertia::render('settings/security', [
            'twoFactorEnabled' => $isConfirmed,
            'twoFactorPending' => $hasSecret && ! $isConfirmed,
            'qrCodeSvg' => $qrCodeSvg,
            'secret' => $secret,
            'recoveryCodes' => $isConfirmed && $user->two_factor_recovery_codes
                ? json_decode(decrypt($user->two_factor_recovery_codes), true)
                : [],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->forceFill([
            'password' => Hash::make($request->input('password')),
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return to_route('security.edit');
    }

    public function enableTwoFactor(Request $request): RedirectResponse
    {
        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        $request->user()->forceFill([
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => null,
        ])->save();

        return back();
    }

    public function confirmTwoFactor(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! $user->two_factor_secret) {
            return back()->withErrors(['code' => __('Two-factor authentication is not being enabled.')]);
        }

        $secret = decrypt($user->two_factor_secret);
        $google2fa = new Google2FA();

        if (! $google2fa->verifyKey($secret, $request->input('code'))) {
            return back()->withErrors(['code' => __('The provided two-factor authentication code was invalid.')]);
        }

        $recoveryCodes = array_map(fn () => Str::random(10).'-'.Str::random(10), range(1, 8));

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Two-factor authentication enabled.')]);

        return back();
    }

    public function disableTwoFactor(Request $request): RedirectResponse
    {
        $request->user()->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Two-factor authentication disabled.')]);

        return back();
    }
}
