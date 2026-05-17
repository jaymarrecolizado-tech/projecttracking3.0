<?php
namespace App\Listeners;
use App\Events\SiteStatusUpdated;
class BroadcastSiteStatusUpdated
{
    public function handle(SiteStatusUpdated $event): void
    {
        // The event itself handles broadcasting via ShouldBroadcast
    }
}
