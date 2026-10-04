<?php

namespace App\Http\Middleware;

use App\Concerns\TenantScope;
use App\Models\Customer;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePortalCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        $customerId = $request->session()->get('portal_customer_id');

        $customer = $customerId ? Customer::query()->withoutGlobalScope(TenantScope::class)->find($customerId) : null;

        if (! $customer) {
            $request->session()->forget('portal_customer_id');

            return redirect()->route('portal.login');
        }

        $request->attributes->set('portalCustomer', $customer);

        return Tenant::runInContext($customer->tenant_id, fn () => $next($request));
    }
}
