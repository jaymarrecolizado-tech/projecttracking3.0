<?php

use App\Http\Controllers\AccomplishmentController;
use App\Http\Controllers\DailyOpsController;
use App\Http\Controllers\DailyStatusController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\MilestoneController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/wallboard', [DashboardController::class, 'wallboard'])->name('wallboard');

    // Split write abilities: create vs edit vs delete resolve through their own
    // policy methods so project-scoped permissions are enforced correctly.
    Route::resource('projects', ProjectController::class)->only(['store'])->middleware('can:create,App\Models\Project');
    Route::resource('projects', ProjectController::class)->only(['update'])->middleware('can:update,project');
    Route::resource('projects', ProjectController::class)->only(['destroy'])->middleware('can:delete,project');
    Route::resource('projects', ProjectController::class)->only(['index', 'show']);

    Route::resource('sites', SiteController::class)->only(['store'])->middleware('can:create,App\Models\Site');
    Route::resource('sites', SiteController::class)->only(['update'])->middleware('can:update,site');
    Route::resource('sites', SiteController::class)->only(['destroy'])->middleware('can:delete,site');
    Route::resource('sites', SiteController::class)->only(['index', 'show']);

    Route::resource('devices', DeviceController::class)->only(['index', 'show'])->middleware('can:devices.view');
    Route::get('devices-labels', [DeviceController::class, 'label'])->name('devices.labels')->middleware('can:devices.view');
    Route::resource('devices', DeviceController::class)->only(['store'])->middleware('can:devices.create');
    Route::resource('devices', DeviceController::class)->only(['update'])->middleware('can:devices.edit');
    Route::resource('devices', DeviceController::class)->only(['destroy'])->middleware('can:devices.delete');
    Route::get('d/{tag}', [DeviceController::class, 'scan'])->name('devices.scan');

    Route::resource('daily-statuses', DailyStatusController::class)->only(['store', 'update', 'destroy'])->middleware('can:daily.edit');
    Route::resource('daily-statuses', DailyStatusController::class)->only(['index', 'show']);

    Route::get('/map', [MapController::class, 'index'])->name('map.index');
    Route::get('/map/geojson', [MapController::class, 'geojson'])->name('map.geojson');

    Route::get('/projects/{project}/sites', [SiteController::class, 'byProject'])->name('projects.sites');
    Route::get('/projects/{project}/milestones', [MilestoneController::class, 'index'])->name('projects.milestones');
    Route::post('/projects/{project}/milestones', [MilestoneController::class, 'store'])->name('projects.milestones.store')->middleware('can:milestone.manage');

    Route::get('/sites/{site}/daily-grid', [DailyStatusController::class, 'grid'])->name('sites.daily-grid');
    Route::post('/daily-statuses/batch', [DailyStatusController::class, 'batchStore'])->name('daily-statuses.batch')->middleware('can:daily.create');

    Route::get('/daily-ops', [DailyOpsController::class, 'index'])->name('daily-ops.index');
    Route::post('/daily-ops/batch', [DailyOpsController::class, 'batch'])->name('daily-ops.batch');

    Route::resource('accomplishments', AccomplishmentController::class)->only(['store', 'update', 'destroy'])->middleware('can:accomplishment.edit');
    Route::resource('accomplishments', AccomplishmentController::class)->only(['index', 'show']);
    Route::get('/sites/{site}/accomplishments', [AccomplishmentController::class, 'bySite'])->name('sites.accomplishments');

    Route::get('/import', [ImportController::class, 'index'])->name('import.index')->middleware('can:import.excel');
    Route::post('/import/upload', [ImportController::class, 'upload'])->name('import.upload')->middleware('can:import.excel');
    Route::get('/import/{batch}', [ImportController::class, 'show'])->name('import.show')->middleware('can:import.excel');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::post('/reports/project/{project}', [ReportController::class, 'projectPdf'])->name('reports.project');
    Route::post('/reports/province', [ReportController::class, 'provincePdf'])->name('reports.province');
    Route::get('/reports/exports/{export}/download', [ReportController::class, 'download'])->name('reports.download');

    // Maintenance tickets — plan §Phase 3 (SLA groundwork)
    Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index')->middleware('can:tickets.manage');
    Route::post('/tickets', [TicketController::class, 'store'])->name('tickets.store')->middleware('can:tickets.manage');
    Route::put('/tickets/{ticket}', [TicketController::class, 'update'])->name('tickets.update')->middleware('can:tickets.manage');
});

require __DIR__.'/auth.php';
