<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('reports:cleanup')->dailyAt('01:30');
Schedule::command('imports:cleanup')->everyFifteenMinutes();
Schedule::command('alerts:down')->everyFifteenMinutes();
Schedule::command('statuses:remind')->dailyAt('07:00');
Schedule::command('statuses:snapshot')->dailyAt('23:00');
Schedule::command('warranty:digest')->weeklyOn(1, '07:00');
Schedule::command('backup:clean')->dailyAt('02:00');
Schedule::command('backup:run')->dailyAt('02:15');
Schedule::command('metrics:prune')->dailyAt('03:00');
