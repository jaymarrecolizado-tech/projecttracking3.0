<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('reports:cleanup')->dailyAt('01:30')->withoutOverlapping();
Schedule::command('imports:cleanup')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('alerts:down')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('statuses:remind')->dailyAt('07:00')->withoutOverlapping();
Schedule::command('statuses:snapshot')->dailyAt('23:00')->withoutOverlapping();
Schedule::command('warranty:digest')->weeklyOn(1, '07:00')->withoutOverlapping();
Schedule::command('backup:clean')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('backup:run')->dailyAt('02:15')->withoutOverlapping();
Schedule::command('backup:monitor')->dailyAt('08:00');
Schedule::command('metrics:prune')->dailyAt('03:00')->withoutOverlapping();
Schedule::command('audit:prune')->monthlyOn(1, '03:30')->withoutOverlapping();
Schedule::command('sanctum:prune-expired --hours=24')->monthlyOn(1, '04:00')->withoutOverlapping();
Schedule::command('metrics:aggregate')->hourlyAt(10)->withoutOverlapping();
Schedule::command('alerts:evaluate')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('reports:scheduled')->monthlyOn(1, '06:00')->withoutOverlapping();
