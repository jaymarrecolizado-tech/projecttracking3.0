<?php
namespace App\Events;
use App\Models\Site;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
class DailyStatusSaved implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;
    public Site $site;
    public function __construct(Site $site)
    {
        $this->site = $site;
    }
    public function broadcastOn(): array
    {
        return [new Channel('sites')];
    }
    public function broadcastAs(): string
    {
        return 'daily.status.saved';
    }
}
