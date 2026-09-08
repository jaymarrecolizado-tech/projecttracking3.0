<?php

use App\Http\Controllers\AccomplishmentController;
use App\Http\Controllers\AlertController;
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
use App\Http\Controllers\SiteEquipmentController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    // One canonical name: two routes named "dashboard" made route:cache()
    // impossible (Plan_revision §Phase 4.3). /dashboard stays as a permanent
    // alias for bookmarks and the post-login redirect.
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::redirect('/dashboard', '/', 301);
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
    Route::resource('sites', SiteController::class)->only(['index', 'show'])->middleware('can:sites.view');

    Route::resource('devices', DeviceController::class)->only(['index', 'show'])->middleware('can:devices.view');
    Route::get('devices-labels', [DeviceController::class, 'label'])->name('devices.labels')->middleware('can:devices.view');
    Route::resource('devices', DeviceController::class)->only(['store'])->middleware('can:devices.create');
    Route::resource('devices', DeviceController::class)->only(['update'])->middleware('can:devices.edit');
    Route::resource('devices', DeviceController::class)->only(['destroy'])->middleware('can:devices.delete');
    Route::get('d/{tag}', [DeviceController::class, 'scan'])->name('devices.scan');

    Route::resource('daily-statuses', DailyStatusController::class)->only(['store', 'update', 'destroy'])->middleware('can:daily.edit');
    Route::resource('daily-statuses', DailyStatusController::class)->only(['index', 'show'])->middleware('can:daily.view');

    // Workflow transitions — content edits stay on daily.edit, but moving a
    // row into APPROVED/LOCKED is an approver-only act (Plan_revision §Phase 2.2).
    Route::post('/daily-statuses/{status}/approve', [DailyStatusController::class, 'approve'])->name('daily-statuses.approve')->middleware('can:daily.approve');
    Route::post('/daily-statuses/{status}/lock', [DailyStatusController::class, 'lock'])->name('daily-statuses.lock')->middleware('can:daily.approve');

    Route::get('/map', [MapController::class, 'index'])->name('map.index');
    Route::get('/map/geojson', [MapController::class, 'geojson'])->name('map.geojson');
    Route::get('/map/filter-options', [MapController::class, 'filterOptions'])->name('map.filter-options');
    Route::get('/map/boundaries', [MapController::class, 'boundaries'])->name('map.boundaries');
    Route::get('/map/coverage', [MapController::class, 'coverage'])->name('map.coverage');
    Route::get('/map/barangay-coverage', [MapController::class, 'barangayCoverage'])->name('map.barangay-coverage');

    Route::get('/projects/{project}/sites', [SiteController::class, 'byProject'])->name('projects.sites')->middleware('can:sites.view');
    Route::get('/projects/{project}/milestones', [MilestoneController::class, 'index'])->name('projects.milestones');
    Route::post('/projects/{project}/milestones', [MilestoneController::class, 'store'])->name('projects.milestones.store')->middleware('can:milestone.manage');

    Route::post('/sites/{site}/equipment', [SiteEquipmentController::class, 'store'])->name('sites.equipment.store')->middleware('can:devices.create');
    Route::delete('/sites/{site}/equipment/{deployment}', [SiteEquipmentController::class, 'destroy'])->name('sites.equipment.destroy')->middleware('can:devices.edit');

    Route::get('/sites/{site}/daily-grid', [DailyStatusController::class, 'grid'])->name('sites.daily-grid')->middleware('can:daily.view');
    Route::post('/daily-statuses/batch', [DailyStatusController::class, 'batchStore'])->name('daily-statuses.batch')->middleware('can:daily.create');

    Route::get('/daily-ops', [DailyOpsController::class, 'index'])->name('daily-ops.index');
    Route::post('/daily-ops/batch', [DailyOpsController::class, 'batch'])->name('daily-ops.batch');

    // Alerts console — audience matches the notification recipients.
    Route::get('/alerts', [AlertController::class, 'index'])->name('alerts.index')->middleware('can:daily.approve');
    Route::post('/alerts/{alert}/acknowledge', [AlertController::class, 'acknowledge'])->name('alerts.acknowledge')->middleware('can:daily.approve');
    Route::post('/alerts/{alert}/resolve', [AlertController::class, 'resolve'])->name('alerts.resolve')->middleware('can:daily.approve');
    Route::post('/alert-rules', [AlertController::class, 'storeRule'])->name('alert-rules.store')->middleware('can:users.manage');
    Route::put('/alert-rules/{rule}', [AlertController::class, 'updateRule'])->name('alert-rules.update')->middleware('can:users.manage');
    Route::delete('/alert-rules/{rule}', [AlertController::class, 'destroyRule'])->name('alert-rules.destroy')->middleware('can:users.manage');

    // User administration
    Route::resource('users', UserController::class)->only(['index', 'store', 'update', 'destroy'])
        ->middleware('can:users.manage');

    Route::resource('accomplishments', AccomplishmentController::class)->only(['store', 'update', 'destroy'])->middleware('can:accomplishment.edit');
    Route::resource('accomplishments', AccomplishmentController::class)->only(['index', 'show'])->middleware('can:accomplishment.view');
    Route::get('/sites/{site}/accomplishments', [AccomplishmentController::class, 'bySite'])->name('sites.accomplishments')->middleware('can:accomplishment.view');

    Route::get('/import', [ImportController::class, 'index'])->name('import.index')->middleware('can:import.excel');
    Route::post('/import/upload', [ImportController::class, 'upload'])->name('import.upload')->middleware('can:import.excel');
    Route::get('/import/{batch}', [ImportController::class, 'show'])->name('import.show')->middleware('can:import.excel');

    // Viewing the console needs reports.view; queueing a PDF consumes storage
    // and CPU, so the four generators require reports.export.
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index')->middleware('can:reports.view');
    Route::post('/reports/project/{project}', [ReportController::class, 'projectPdf'])->name('reports.project')->middleware('can:reports.export');
    Route::post('/reports/province', [ReportController::class, 'provincePdf'])->name('reports.province')->middleware('can:reports.export');
    Route::post('/reports/site-type', [ReportController::class, 'siteTypePdf'])->name('reports.site-type')->middleware('can:reports.export');
    Route::post('/reports/barangay-coverage', [ReportController::class, 'barangayCoveragePdf'])->name('reports.barangay-coverage')->middleware('can:reports.export');
    Route::get('/reports/exports/{export}/download', [ReportController::class, 'download'])->name('reports.download')->middleware('can:reports.view');
    Route::post('/reports/exports/{export}/retry', [ReportController::class, 'retry'])->name('reports.retry')->middleware('can:reports.export');

    // Maintenance tickets — plan §Phase 3 (SLA groundwork)
    Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index')->middleware('can:tickets.manage');
    Route::post('/tickets', [TicketController::class, 'store'])->name('tickets.store')->middleware('can:tickets.manage');
    Route::put('/tickets/{ticket}', [TicketController::class, 'update'])->name('tickets.update')->middleware('can:tickets.manage');
});

require __DIR__.'/auth.php';
