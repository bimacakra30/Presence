<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Presence;
use App\Models\Permit;
use App\Observers\PresenceObserver;
use App\Observers\PermitObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register observers
        Presence::observe(PresenceObserver::class);
        Permit::observe(PermitObserver::class);
    }
}
