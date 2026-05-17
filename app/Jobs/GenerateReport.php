<?php
namespace App\Jobs;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
class GenerateReport implements ShouldQueue
{
    use Dispatchable, Queueable;
    public string $type;
    public array $params;
    public int $userId;
    public function __construct(string $type, array $params, int $userId)
    {
        $this->type = $type;
        $this->params = $params;
        $this->userId = $userId;
    }
    public function handle(): void
    {
        // Report generation logic runs here
    }
}
