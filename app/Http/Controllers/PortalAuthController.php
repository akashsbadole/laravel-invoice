<?php

namespace App\Http\Controllers;

use App\Mail\CustomerPortalLinkMail;
use App\Models\Customer;
use App\Models\CustomerPortalToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class PortalAuthController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('portal/login', [
            'status' => session('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $customer = Customer::query()->where('email', $validated['email'])->first();

        // Always respond identically so emails can't be enumerated.
        if ($customer && $customer->email) {
            $token = new CustomerPortalToken([
                'customer_id' => $customer->id,
                'token' => CustomerPortalToken::generateToken(),
                'expires_at' => now()->addMinutes(30),
            ]);
            $token->tenant_id = $customer->tenant_id;
            $token->save();

            Mail::to($customer->email)->send(new CustomerPortalLinkMail($customer, $token->token));
        }

        return back()->with('status', __('If an account exists for that email, a sign-in link is on its way.'));
    }

    public function verify(Request $request, string $token): RedirectResponse
    {
        $loginToken = CustomerPortalToken::query()->where('token', $token)->first();

        if (! $loginToken || ! $loginToken->isUsable()) {
            return to_route('portal.login')->withErrors(['email' => __('This sign-in link is invalid or expired.')]);
        }

        $loginToken->update(['used_at' => now()]);

        $request->session()->put('portal_customer_id', $loginToken->customer_id);
        $request->session()->regenerate();

        return to_route('portal.dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget('portal_customer_id');

        return to_route('portal.login');
    }
}
