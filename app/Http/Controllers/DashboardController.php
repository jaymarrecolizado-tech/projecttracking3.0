<?php

namespace App\Http\Controllers;

use App\Services\ReportingService;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(ReportingService $reportingService)
    {
        return Inertia::render('Dashboard', ['stats' => $reportingService->getDashboardStats()]);
    }

    /** NOC wallboard — dense auto-refreshing status screen. */
    public function wallboard(ReportingService $reportingService)
    {
        return Inertia::render('Wallboard', ['stats' => $reportingService->getWallboardStats()]);
    }
}
