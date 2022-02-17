<?php

namespace Hammerstone\Sidecar\PHP\Tests\Support\App\Providers;

use Hammerstone\Sidecar\PHP\Tests\Support\App\Events\AttemptsReachedEvent;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Events\FailingEvent;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Events\ImplementsBothRunInLambdaAndDoNotRunInLambdaEvent;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Events\ImplementsDoNotRunInLambdaEvent;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Events\ImplementsRunInLambdaEvent;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Events\PassingEvent;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Events\ReleasingEvent;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Events\ReleasingWithDelayEvent;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Listeners\AttemptsReachedListener;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Listeners\FailingListener;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Listeners\ImplementsBothRunInLambdaAndDoNotRunInLambdaListener;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Listeners\ImplementsDoNotRunInLambdaListener;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Listeners\ImplementsRunInLambdaListener;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Listeners\PassingListener;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Listeners\ReleasingListener;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Listeners\ReleasingWithDelayListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        AttemptsReachedEvent::class => [
            AttemptsReachedListener::class,
        ],
        FailingEvent::class => [
            FailingListener::class,
        ],
        ImplementsBothRunInLambdaAndDoNotRunInLambdaEvent::class => [
            ImplementsBothRunInLambdaAndDoNotRunInLambdaListener::class,
        ],
        ImplementsDoNotRunInLambdaEvent::class => [
            ImplementsDoNotRunInLambdaListener::class,
        ],
        ImplementsRunInLambdaEvent::class => [
            ImplementsRunInLambdaListener::class,
        ],
        PassingEvent::class => [
            PassingListener::class,
        ],
        ReleasingEvent::class => [
            ReleasingListener::class,
        ],
        ReleasingWithDelayEvent::class => [
            ReleasingWithDelayListener::class,
        ],
    ];
}
