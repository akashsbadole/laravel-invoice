<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps platform operators out of a tenant's own screens.
 *
 * A super admin has no tenant_id, so TenantScope::apply() finds no tenant
 * context and silently applies no filter. Every tenant-scoped list route
 * would then run unscoped and hand the operator every tenant's invoices,
 * customers, payments and GST figures in one page. Their own panel lives under
 * /admin and lifts scopes deliberately, one tenant at a time.
 *
 * Impersonation is unaffected: it signs the operator in *as* the tenant's
 * staff member (PlatformController::impersonate), so auth()->user() carries
 * that user's tenant_id.
 */
class EnsureTenantUser
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(
            $request->user() && $request->user()->tenant_id === null,
            403,
            'Platform accounts cannot open a tenant’s records.',
        );

        return $next($request);
    }
}
