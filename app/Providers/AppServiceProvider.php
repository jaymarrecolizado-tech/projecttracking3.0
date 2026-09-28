<?php

namespace App\Providers;

use App\Listeners\CheckSystemHealth;
use App\Models\Site;
use App\Models\SiteAccomplishment;
use App\Models\SiteDailyStatus;
use App\Observers\AccomplishmentObserver;
use App\Observers\SiteObserver;
use App\Observers\SiteStatusEventObserver;
use App\Services\Telegram;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
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

        // /up is what an external uptime monitor pings. Laravel's endpoint only
        // proves PHP is alive, so the listener below is what turns it into an
        // availability signal (see App\Listeners\CheckSystemHealth).
        Event::listen(DiagnosingHealth::class, CheckSystemHealth::class);

        // failed_jobs must not fail silently (Plan_revision §Phase 4.6): every
        // permanent queue failure is logged loudly and pushed to the ops
        // Telegram channel when it is configured.
        Queue::failing(function (JobFailed $event): void {
            Log::error('Queue job failed permanently.', [
                'job' => $event->job->resolveName(),
                'queue' => $event->job->getQueue(),
                'error' => $event->exception->getMessage(),
            ]);

            $telegram = app(Telegram::class);
            if ($telegram->configured()) {
                $telegram->sendMessage(
                    "⚠️ Queue job failed permanently\n\nJob: {$event->job->resolveName()}\nQueue: {$event->job->getQueue()}\nError: {$event->exception->getMessage()}"
                );
            }
        });

        // Used by $middleware->throttleApi('api') in bootstrap/app.php.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        // The public survey POST (Plan.md S1). Tighter than the API limiter
        // because it is the one unauthenticated write in the app, and a
        // scripted flood would skew a site's average. Layered: a burst limit
        // and a daily ceiling for the same address.
        RateLimiter::for('survey', function (Request $request) {
            return [
                Limit::perMinute(5)->by($request->ip()),
                Limit::perDay(30)->by($request->ip()),
            ];
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
