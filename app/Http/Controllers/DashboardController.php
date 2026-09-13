<?php

namespace App\Http\Controllers;

use App\Services\ReportingService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(ReportingService $reportingService): Response
    {
        return Inertia::render('Dashboard', ['stats' => $reportingService->getDashboardStats()]);
    }

    /** NOC wallboard — dense auto-refreshing status screen. */
    public function wallboard(ReportingService $reportingService): Response
    {
        return Inertia::render('Wallboard', ['stats' => $reportingService->getWallboardStats()]);
    }
}
