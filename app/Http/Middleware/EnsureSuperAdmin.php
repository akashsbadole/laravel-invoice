<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the platform admin panel.
 *
 * A super admin has no tenant of their own, so they are let through with
 * every tenant scope lifted; the panel queries are explicit about which
 * tenant they are acting on.
 */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user?->isSuperAdmin(), 403, 'Super admin access only.');

        return $next($request);
    }
}
