<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscribed
{
    /**
     * Routes that stay reachable without an active subscription.
     *
     * @var list<string>
     */
    private array $exceptRouteNames = [
        'billing.index',
        'billing.checkout',
        'billing.webhook',
        'logout',
        'login',
        'register',
        'password.request',
        'password.email',
        'password.reset',
        'password.update',
    ];

    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->tenant_id) {
            return $next($request);
        }

        $route = $request->route();

        if ($route && in_array($route->getName(), $this->exceptRouteNames, true)) {
            return $next($request);
        }

        // Public share links and auth pages are registered without auth
        // middleware, so they never reach here as guests.
        if (! $this->subscriptions->usablePlan($user->tenant)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                abort(402, 'Subscription required.');
            }

            return redirect()->route('billing.index')->withErrors([
                'subscription' => 'Your trial or subscription has expired. Please subscribe to continue.',
            ]);
        }

        return $next($request);
    }
}
