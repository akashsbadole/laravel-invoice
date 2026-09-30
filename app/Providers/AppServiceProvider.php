<?php

namespace App\Providers;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\ServiceProvider;
use Inertia\ResponseFactory;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // `Inertia::flash('toast', ...)` used across controllers maps to
        // regular session flashing. The inertia-laravel adapter ships no
        // such helper, so it is registered here as a macro.
        if (! ResponseFactory::hasMacro('flash')) {
            ResponseFactory::macro('flash', function (string $key, mixed $value): void {
                Session::flash($key, $value);
            });
        }
    }
}
