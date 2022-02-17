<?php

namespace Hammerstone\Sidecar\PHP\Tests\Support\App\Listeners;

use Hammerstone\Sidecar\PHP\Tests\Support\App\Events\AttemptsReachedEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class AttemptsReachedListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(AttemptsReachedEvent $event)
    {
    }
}
