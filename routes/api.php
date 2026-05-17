<?php
use App\Http\Controllers\Api\SiteApiController;
use App\Http\Controllers\Api\DailyStatusApiController;
use App\Http\Controllers\Api\MapApiController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/sites', [SiteApiController::class, 'index']);
    Route::get('/sites/{site}', [SiteApiController::class, 'show']);
    Route::get('/map/sites', [MapApiController::class, 'sites']);
    Route::get('/map/project/{project}', [MapApiController::class, 'projectSites']);
    Route::get('/daily-statuses', [DailyStatusApiController::class, 'index']);
    Route::get('/daily-statuses/site/{site}', [DailyStatusApiController::class, 'bySite']);
});
