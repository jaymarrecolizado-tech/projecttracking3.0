<?php
namespace App\Services;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteDailyStatus;
use App\Models\SiteAccomplishment;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
class ReportingService
{
    public function generateProjectSummaryPdf(Project $project): \Barryvdh\DomPDF\PDF
    {
        $sites = $project->sites()->with('latestDailyStatus')->get();
        $stats = [
            'total' => $sites->count(),
            'active' => $sites->where('status', 'active')->count(),
            'inactive' => $sites->where('status', 'inactive')->count(),
            'planned' => $sites->where('status', 'planned')->count(),
        ];
        if ($project->report_type === 'freewifi') {
            $upCount = SiteDailyStatus::whereHas('site', fn($q) => $q->where('project_id', $project->id))
                ->where('status', 'UP')->whereDate('date', today())->count();
            $stats['up_today'] = $upCount;
        }
        return Pdf::loadView('reports.project-summary', compact('project', 'sites', 'stats'));
    }
    public function generateProvinceReport(string $province, ?int $projectId = null): \Barryvdh\DomPDF\PDF
    {
        $query = Site::where('province', $province)->with('project');
        if ($projectId) $query->where('project_id', $projectId);
        $sites = $query->get();
        $grouped = $sites->groupBy(fn($s) => $s->municipality ?? 'Unknown');
        return Pdf::loadView('reports.province-summary', compact('province', 'sites', 'grouped'));
    }
    public function getDashboardStats(): array
    {
        return [
            'total_projects' => Project::count(),
            'total_sites' => Site::count(),
            'active_sites' => Site::where('status', 'active')->count(),
            'total_up_today' => SiteDailyStatus::where('status', 'UP')->whereDate('date', today())->count(),
            'recent_imports' => \App\Models\FreewifiImportBatch::with('importer:id,name')->latest()->take(5)->get(),
        ];
    }
}
