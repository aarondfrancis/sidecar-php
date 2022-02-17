<?php

namespace Hammerstone\Sidecar\PHP\Tests\Support\App\Listeners;

use Hammerstone\Sidecar\PHP\Tests\Support\App\Events\ReleasingWithDelayEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ReleasingWithDelayListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(ReleasingWithDelayEvent $event)
    {
        $this->release(10);
    }
}
