<?php

namespace Hammerstone\Sidecar\PHP\Tests\Support\App\Listeners;

use Exception;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Events\FailingEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class FailingListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(FailingEvent $event)
    {
        throw new Exception('A cooked goose for everyone!');
    }
}
