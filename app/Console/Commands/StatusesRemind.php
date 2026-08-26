<?php

namespace App\Console\Commands;

use App\Mail\StatusReminderMail;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class StatusesRemind extends Command
{
    protected $signature = 'statuses:remind';

    protected $description = 'Email encoders the number of sites still missing today\'s status report';

    public function handle(): int
    {
        $projects = Project::where('is_active', true)->get();

        // user email => [project name => missing count]
        $digest = [];

        foreach ($projects as $project) {
            $missing = Site::where('project_id', $project->id)
                ->where('status', 'active')
                ->whereDoesntHave('dailyStatuses', fn ($q) => $q->whereDate('date', today()))
                ->count();
            if ($missing === 0) {
                continue;
            }

            $recipients = User::query()
                ->whereHas('roles.permissions', fn ($q) => $q->where('permissions.name', 'daily.create'))
                ->get()
                ->filter(fn (User $user) => $user->hasPermission('daily.create', $project->id));

            foreach ($recipients as $user) {
                $digest[$user->email][$project->name] = $missing;
            }
        }

        foreach ($digest as $email => $perProject) {
            Mail::to($email)->send(new StatusReminderMail(
                today()->toDateString(),
                $perProject,
                route('daily-ops.index'),
            ));
            $this->info("Reminded {$email} (".array_sum($perProject).' sites).');
        }

        if ($digest === []) {
            $this->info('Everything reported — no reminders sent.');
        }

        return self::SUCCESS;
    }
}
