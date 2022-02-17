<?php

namespace Hammerstone\Sidecar\PHP\Tests\Support\App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReleasingEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function handle()
    {
        $this->release();
    }
}
