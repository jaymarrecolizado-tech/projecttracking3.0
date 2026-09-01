<?php

namespace App\Console\Commands;

use App\Models\Device;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class WarrantyDigest extends Command
{
    protected $signature = 'warranty:digest {--days=30 : Look-ahead window} {--email= : Send summary to this address}';

    protected $description = 'Summarize devices whose warranty expires soon (log + optional email)';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $expiring = Device::with('deviceModel:id,manufacturer,model_name')
            ->whereNotNull('warranty_until')
            ->whereBetween('warranty_until', [now(), now()->addDays($days)])
            ->orderBy('warranty_until')
            ->get();

        $this->table(
            ['Asset tag', 'Model', 'Warranty until'],
            $expiring->map(fn ($d) => [
                $d->asset_tag,
                "{$d->deviceModel->manufacturer} {$d->deviceModel->model_name}",
                $d->warranty_until->toDateString(),
            ]),
        );

        if ($email = ($this->option('email') ?: config('monitoring.watchdog_email'))) {
            Mail::raw(
                "Warranty digest: {$expiring->count()} device(s) expire within {$days} days.\n\n".
                $expiring->map(fn ($d) => "- {$d->asset_tag}: expires {$d->warranty_until->toDateString()}")->implode("\n"),
                fn ($m) => $m->to($email)->subject("[FPIAP · FreeWiFi] Warranty digest — {$expiring->count()} devices"),
            );
            $this->info("Digest mailed to {$email}.");
        }

        return self::SUCCESS;
    }
}
