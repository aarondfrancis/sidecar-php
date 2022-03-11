<?php

namespace Hammerstone\Sidecar\PHP\Tests\Support\App\Providers;

use Hammerstone\Sidecar\PHP\Tests\Support\App\Events\AttemptsReachedEvent;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Events\FailedEvent;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Events\ImplementsBothRunInLambdaAndDoNotRunInLambdaEvent;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Events\ImplementsDoNotRunInLambdaEvent;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Events\ImplementsRunInLambdaEvent;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Events\PassedEvent;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Events\ReleasedEvent;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Events\ReleasedWithDelayEvent;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Listeners\AttemptsReachedListener;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Listeners\FailedListener;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Listeners\ImplementsBothRunInLambdaAndDoNotRunInLambdaListener;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Listeners\ImplementsDoNotRunInLambdaListener;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Listeners\ImplementsRunInLambdaListener;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Listeners\PassedListener;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Listeners\ReleasedListener;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Listeners\ReleasedWithDelayListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        AttemptsReachedEvent::class => [
            AttemptsReachedListener::class,
        ],
        FailedEvent::class => [
            FailedListener::class,
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
        PassedEvent::class => [
            PassedListener::class,
        ],
        ReleasedEvent::class => [
            ReleasedListener::class,
        ],
        ReleasedWithDelayEvent::class => [
            ReleasedWithDelayListener::class,
        ],
        ThrownEvent::class => [
            ThrownListener::class,
        ],
    ];
}
