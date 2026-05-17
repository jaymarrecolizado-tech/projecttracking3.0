<?php
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\DailyStatusController;
use App\Http\Controllers\AccomplishmentController;
use App\Http\Controllers\MilestoneController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('projects', ProjectController::class)->except(['index', 'show'])->middleware('can:create,App\Models\Project');
    Route::resource('projects', ProjectController::class)->only(['index', 'show']);

    Route::resource('sites', SiteController::class)->except(['index', 'show'])->middleware('can:create,App\Models\Site');
    Route::resource('sites', SiteController::class)->only(['index', 'show']);

    Route::resource('daily-statuses', DailyStatusController::class)->only(['store', 'update', 'destroy'])->middleware('can:daily.edit');
    Route::resource('daily-statuses', DailyStatusController::class)->only(['index', 'show']);

    Route::get('/map', [MapController::class, 'index'])->name('map.index');
    Route::get('/map/geojson', [MapController::class, 'geojson'])->name('map.geojson');

    Route::get('/projects/{project}/sites', [SiteController::class, 'byProject'])->name('projects.sites');
    Route::get('/projects/{project}/milestones', [MilestoneController::class, 'index'])->name('projects.milestones');
    Route::post('/projects/{project}/milestones', [MilestoneController::class, 'store'])->name('projects.milestones.store')->middleware('can:milestone.manage');

    Route::get('/sites/{site}/daily-grid', [DailyStatusController::class, 'grid'])->name('sites.daily-grid');
    Route::post('/daily-statuses/batch', [DailyStatusController::class, 'batchStore'])->name('daily-statuses.batch')->middleware('can:daily.create');

    Route::resource('accomplishments', AccomplishmentController::class)->only(['store', 'update', 'destroy'])->middleware('can:accomplishment.edit');
    Route::resource('accomplishments', AccomplishmentController::class)->only(['index', 'show']);
    Route::get('/sites/{site}/accomplishments', [AccomplishmentController::class, 'bySite'])->name('sites.accomplishments');

    Route::get('/import', [ImportController::class, 'index'])->name('import.index')->middleware('can:import.excel');
    Route::post('/import/upload', [ImportController::class, 'upload'])->name('import.upload')->middleware('can:import.excel');
    Route::get('/import/{batch}', [ImportController::class, 'show'])->name('import.show')->middleware('can:import.excel');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::post('/reports/project/{project}', [ReportController::class, 'projectPdf'])->name('reports.project');
    Route::post('/reports/province', [ReportController::class, 'provincePdf'])->name('reports.province');
});

require __DIR__.'/auth.php';
