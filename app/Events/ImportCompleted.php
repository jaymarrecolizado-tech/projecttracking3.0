<?php
namespace App\Events;
use App\Models\FreewifiImportBatch;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
class ImportCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;
    public FreewifiImportBatch $batch;
    public function __construct(FreewifiImportBatch $batch)
    {
        $this->batch = $batch;
    }
    public function broadcastOn(): array
    {
        return [new Channel('imports')];
    }
}
