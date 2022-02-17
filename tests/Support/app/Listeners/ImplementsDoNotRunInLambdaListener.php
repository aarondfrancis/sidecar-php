<?php

namespace Hammerstone\Sidecar\PHP\Tests\Support\App\Listeners;

use Hammerstone\Sidecar\PHP\Contracts\Queue\DoNotRunInLambda;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Events\ImplementsDoNotRunInLambdaEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ImplementsDoNotRunInLambdaListener implements ShouldQueue, DoNotRunInLambda
{
    use InteractsWithQueue;

    public function handle(ImplementsDoNotRunInLambdaEvent $event)
    {
    }
}
