<?php

namespace App\Http\Middleware;

use App\Support\Industry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hides trade-specific modules from tenants whose industry has no use for
 * them — a tiles shop should never see the metal-rates screen.
 */
class EnsureIndustryAllows
{
    public function handle(Request $request, Closure $next, string $capability): Response
    {
        $industry = Industry::current();

        abort_unless($this->allows($industry, $capability), 404);

        return $next($request);
    }

    protected function allows(string $industry, string $capability): bool
    {
        return match ($capability) {
            'metal_rates' => Industry::usesMetalRates($industry),
            default => true,
        };
    }
}
