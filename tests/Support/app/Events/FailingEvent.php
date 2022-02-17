<?php

namespace Hammerstone\Sidecar\PHP\Tests\Support\App\Events;

use Exception;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FailingEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function handle()
    {
        throw new Exception('I am genuinely sorry about the window.');
    }
}
