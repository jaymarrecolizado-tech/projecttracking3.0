<?php

use App\Http\Controllers\Api\DailyStatusApiController;
use App\Http\Controllers\Api\HeartbeatController;
use App\Http\Controllers\Api\MapApiController;
use App\Http\Controllers\Api\PublicSurveyController;
use App\Http\Controllers\Api\SiteApiController;
use Illuminate\Support\Facades\Route;

// Public survey surface (Plan_UI.md Part II). The JSON face consumed by the
// Next.js public site. Unauthenticated by design — the same cohort the Inertia
// survey serves — so every route is throttled and the POST is signed.
//
// `throttle:survey` is 5/min AND 30/day per IP, keyed on the real client IP,
// which is why the browser posts here directly rather than through a proxy.
Route::prefix('public/surveys')->name('api.public.surveys.')->group(function () {
    Route::get('/{siteCode}', [PublicSurveyController::class, 'show'])
        ->name('show')
        ->middleware('throttle:60,1');
    Route::get('/{siteCode}/thanks', [PublicSurveyController::class, 'thanks'])
        ->name('thanks')
        ->middleware('throttle:60,1');
    Route::post('/{siteCode}', [PublicSurveyController::class, 'store'])
        ->name('store')
        ->middleware(['signed', 'throttle:survey']);
});

Route::middleware('auth:sanctum')->group(function () {
    // Read endpoints resolve through the same view permissions as the web
    // console — a bare Sanctum token is not a read grant by itself.
    Route::middleware('can:sites.view')->group(function () {
        Route::get('/sites', [SiteApiController::class, 'index']);
        Route::get('/sites/{site}', [SiteApiController::class, 'show']);
        Route::get('/map/sites', [MapApiController::class, 'sites']);
        Route::get('/map/project/{project}', [MapApiController::class, 'projectSites']);
    });
    Route::middleware('can:daily.view')->group(function () {
        Route::get('/daily-statuses', [DailyStatusApiController::class, 'index']);
        Route::get('/daily-statuses/site/{site}', [DailyStatusApiController::class, 'bySite']);
    });

    // Field-probe heartbeat ingest.
    Route::post('/heartbeat', [HeartbeatController::class, 'store'])->middleware('throttle:60,1');
});
