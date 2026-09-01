<?php

namespace App\Providers;

use App\Models\Site;
use App\Models\SiteAccomplishment;
use App\Models\SiteDailyStatus;
use App\Observers\AccomplishmentObserver;
use App\Observers\SiteObserver;
use App\Observers\SiteStatusEventObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Schema::defaultStringLength(100);
        Site::observe(SiteObserver::class);
        SiteAccomplishment::observe(AccomplishmentObserver::class);
        SiteDailyStatus::observe(SiteStatusEventObserver::class);
        Vite::prefetch(concurrency: 3);

        // Used by $middleware->throttleApi('api') in bootstrap/app.php.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        // Register string-based permission Gates used by can: middleware on routes
        $permissions = [
            'sites.create', 'sites.view', 'sites.edit', 'sites.delete',
            'devices.view', 'devices.create', 'devices.edit', 'devices.delete',
            'daily.create', 'daily.view', 'daily.edit', 'daily.submit', 'daily.approve',
            'accomplishment.create', 'accomplishment.view', 'accomplishment.edit', 'accomplishment.submit',
            'milestone.manage', 'import.excel',
            'reports.view', 'reports.export',
            'tickets.manage',
            'users.manage', 'audit.view', 'projects.manage',
        ];
        foreach ($permissions as $permission) {
            Gate::define($permission, fn ($user) => $user->hasPermission($permission));
        }
    }
}
