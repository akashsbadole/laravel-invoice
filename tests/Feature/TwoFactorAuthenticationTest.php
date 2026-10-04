<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_factor_setup_flow(): void
    {
        $user = $this->adminFor();

        // 1. Enable 2FA setup
        $this->actingAs($user)
            ->post(route('two-factor.enable'))
            ->assertRedirect();

        $user->refresh();
        $this->assertNotNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);

        // 2. View setup QR code page
        $google2fa = new Google2FA();
        $secret = decrypt($user->two_factor_secret);
        $code = $google2fa->getCurrentOtp($secret);

        // 3. Confirm 2FA code
        $this->actingAs($user)
            ->post(route('two-factor.confirm'), ['code' => $code])
            ->assertRedirect();

        $user->refresh();
        $this->assertNotNull($user->two_factor_confirmed_at);
        $this->assertNotNull($user->two_factor_recovery_codes);

        // 4. Login challenge required
        $this->post(route('logout'));

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])
        ->assertRedirect(route('two-factor.login'));

        // 5. Verify challenge code
        $this->post(route('two-factor.verify'), ['code' => $code])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);

        // 6. Disable 2FA
        $this->actingAs($user)
            ->delete(route('two-factor.disable'))
            ->assertRedirect();

        $user->refresh();
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);
    }
}
