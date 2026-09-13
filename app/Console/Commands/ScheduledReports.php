<?php

namespace App\Console\Commands;

use App\Jobs\GenerateReport;
use App\Models\ReportExport;
use App\Models\Site;
use App\Models\User;
use App\Services\Telegram;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ScheduledReports extends Command
{
    protected $signature = 'reports:scheduled
        {--month= : YYYY-MM to report on (defaults to the previous month)}
        {--province= : Limit to a single province}
        {--email= : Extra mail recipient (falls back to REPORT_SCHEDULED_EMAIL, then WATCHDOG_EMAIL)}
        {--force : Re-queue even if this province+period was already exported}';

    protected $description = 'Queue the monthly provincial PDF pack for the previous month, then notify by mail/Telegram';

    public function handle(Telegram $telegram): int
    {
        $month = $this->option('month') ?: now()->subMonthNoOverflow()->format('Y-m');
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $this->error("Invalid --month '{$month}'; expected YYYY-MM.");

            return self::FAILURE;
        }
        $from = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->toDateString();
        $to = Carbon::createFromFormat('Y-m', $month)->endOfMonth()->toDateString();

        $owner = User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->first()
            ?? User::first();
        if (! $owner) {
            $this->error('No users exist yet — scheduled exports need an owner for the export rows.');

            return self::FAILURE;
        }

        $provinces = Site::query()
            ->whereNotNull('province')->where('province', '<>', '')
            ->when($this->option('province'), fn ($q, $p) => $q->where('province', $p))
            ->distinct()->orderBy('province')->pluck('province');

        if ($provinces->isEmpty()) {
            $this->warn('No provinces with sites — nothing queued.');

            return self::SUCCESS;
        }

        $queued = 0;
        $skipped = 0;
        foreach ($provinces as $province) {
            $already = ! $this->option('force') && ReportExport::where('type', 'province')
                ->where('params->province', $province)
                ->where('params->from', $from)
                ->where('params->to', $to)
                ->whereIn('status', ['PENDING', 'PROCESSING', 'DONE'])
                ->exists();
            if ($already) {
                $skipped++;
                $this->line("Skipped {$province} — already exported for {$month} (use --force to re-queue).");

                continue;
            }

            $export = ReportExport::create([
                'user_id' => $owner->id,
                'type' => 'province',
                'params' => ['province' => $province, 'project_id' => null, 'from' => $from, 'to' => $to],
                'download_name' => Str::slug("province-{$province}-{$month}").'.pdf',
            ]);
            GenerateReport::dispatch($export);
            $queued++;
            $this->line("Queued {$province} ({$from} → {$to}).");
        }

        $label = Carbon::createFromFormat('Y-m', $month)->format('F Y');
        $summary = "Monthly provincial packs for {$label}: {$queued} queued, {$skipped} skipped.";
        if ($email = ($this->option('email') ?: config('monitoring.reports_email'))) {
            Mail::raw($summary, fn ($m) => $m->to($email)->subject("[FPIAP · FreeWiFi] Monthly reports — {$label}"));
            $this->info("Summary mailed to {$email}.");
        }
        if ($telegram->configured()) {
            $telegram->sendMessage($summary);
            $this->info('Summary sent to Telegram.');
        }

        return self::SUCCESS;
    }
}
