<?php

namespace App\Http\Middleware;

use App\Models\Customer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePortalCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        $customerId = $request->session()->get('portal_customer_id');

        $customer = $customerId ? Customer::query()->find($customerId) : null;

        if (! $customer) {
            $request->session()->forget('portal_customer_id');

            return redirect()->route('portal.login');
        }

        $request->attributes->set('portalCustomer', $customer);

        return $next($request);
    }
}
