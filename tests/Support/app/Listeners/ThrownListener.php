<?php

namespace Hammerstone\Sidecar\PHP\Tests\Support\App\Listeners;

use Exception;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Events\FailedEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ThrownListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(FailedEvent $event)
    {
        throw new Exception('A cooked goose for everyone!');
    }

    public function failed($error)
    {
        test()->expect($error->getMessage())->toBe('A cooked goose for everyone!');
    }
}
