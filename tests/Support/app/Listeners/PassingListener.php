<?php

namespace Hammerstone\Sidecar\PHP\Tests\Support\App\Listeners;

use Hammerstone\Sidecar\PHP\Tests\Support\App\Events\PassingEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class PassingListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(PassingEvent $event)
    {
    }
}
