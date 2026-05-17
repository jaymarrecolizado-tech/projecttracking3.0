<?php

namespace App\Providers;

use App\Models\Site;
use App\Models\SiteAccomplishment;
use App\Observers\SiteObserver;
use App\Observers\AccomplishmentObserver;
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
        Vite::prefetch(concurrency: 3);
    }
}
