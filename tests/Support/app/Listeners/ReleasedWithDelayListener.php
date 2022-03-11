<?php

namespace Hammerstone\Sidecar\PHP\Tests\Support\App\Listeners;

use Hammerstone\Sidecar\PHP\Tests\Support\App\Events\ReleasedWithDelayEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ReleasedWithDelayListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(ReleasedWithDelayEvent $event)
    {
        $this->release(10);
    }
}
