<?php

namespace Hammerstone\Sidecar\PHP\Tests\Support\App\Listeners;

use Hammerstone\Sidecar\PHP\Tests\Support\App\Events\ReleasingEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ReleasingListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(ReleasingEvent $event)
    {
        $this->release();
    }
}
