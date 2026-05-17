<?php
namespace App\Http\Controllers;
use App\Services\ReportingService;
use Inertia\Inertia;
class DashboardController extends Controller
{
    public function index(ReportingService $reportingService)
    {
        $stats = $reportingService->getDashboardStats();
        return Inertia::render('Dashboard', ['stats' => $stats]);
    }
}
