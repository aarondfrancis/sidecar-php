<?php

namespace Hammerstone\Sidecar\PHP\Tests\Datasets;

use Facades\Hammerstone\Sidecar\PHP\Tests\Support\App\PayService\Pay;
use Facades\Hammerstone\Sidecar\PHP\Tests\Support\App\PayService\PayAttemptsReached;
use Facades\Hammerstone\Sidecar\PHP\Tests\Support\App\PayService\PayReleasing;
use Facades\Hammerstone\Sidecar\PHP\Tests\Support\App\PayService\PayReleasingWithDelay;
use Facades\Illuminate\Mail\PendingMail;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Events\AttemptsReachedEvent;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Events\FailingEvent;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Events\ImplementsBothRunInLambdaAndDoNotRunInLambdaEvent;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Events\ImplementsDoNotRunInLambdaEvent;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Events\ImplementsRunInLambdaEvent;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Events\PassingEvent;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Events\ReleasingEvent;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Events\ReleasingWithDelayEvent;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Jobs\AttemptsReachedJob;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Jobs\FailingJob;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Jobs\ImplementsBothRunInLambdaAndDoNotRunInLambdaJob;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Jobs\ImplementsDoNotRunInLambdaJob;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Jobs\ImplementsRunInLambdaJob;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Jobs\PassingJob;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Jobs\ReleasingJob;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Jobs\ReleasingWithDelayJob;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Listeners\AttemptsReachedListener;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Listeners\FailingListener;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Listeners\ImplementsBothRunInLambdaAndDoNotRunInLambdaListener;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Listeners\ImplementsDoNotRunInLambdaListener;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Listeners\ImplementsRunInLambdaListener;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Listeners\PassingListener;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Listeners\ReleasingListener;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Listeners\ReleasingWithDelayListener;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Mail\AttemptsReachedMailable;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Mail\FailingMailable;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Mail\ImplementsBothRunInLambdaAndDoNotRunInLambdaMailable;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Mail\ImplementsDoNotRunInLambdaMailable;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Mail\ImplementsRunInLambdaMailable;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Mail\PassingMailable;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Mail\ReleasingMailable;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Mail\ReleasingWithDelayMailable;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Notifications\AttemptsReachedNotification;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Notifications\FailingNotification;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Notifications\ImplementsBothRunInLambdaAndDoNotRunInLambdaNotification;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Notifications\ImplementsDoNotRunInLambdaNotification;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Notifications\ImplementsRunInLambdaNotification;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Notifications\PassingNotification;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Notifications\ReleasingNotification;
use Hammerstone\Sidecar\PHP\Tests\Support\App\Notifications\ReleasingWithDelayNotification;
use Hammerstone\Sidecar\PHP\Tests\Support\App\PayService\Payables\FailingPayable;
use Hammerstone\Sidecar\PHP\Tests\Support\App\PayService\Payables\ImplementsBothRunInLambdaAndDoNotRunInLambdaPayable;
use Hammerstone\Sidecar\PHP\Tests\Support\App\PayService\Payables\ImplementsDoNotRunInLambdaPayable;
use Hammerstone\Sidecar\PHP\Tests\Support\App\PayService\Payables\ImplementsRunInLambdaPayable;
use Hammerstone\Sidecar\PHP\Tests\Support\App\PayService\Payables\PassingPayable;
use Hammerstone\Sidecar\PHP\Tests\Support\QueueTestHelper;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Support\Facades\Notification;

$succeeds = [
    'job' => fn () => new QueueTestHelper(new PassingJob, fn ($queueable) => dispatch($queueable), false),
    // 'event' => fn () => new QueueTestHelper(new PassingEvent, fn ($queueable) => dispatch($queueable), false),
    // 'listener' => fn () => new QueueTestHelper(new PassingListener, fn ($queueable) => dispatch($queueable), false),
    'mailable' => fn () => new QueueTestHelper((new PassingMailable)->to('example@email.com'), fn ($queueable) => PendingMail::queue($queueable), false),
    'notification' => fn () => new QueueTestHelper(new PassingNotification, fn ($queueable) => Notification::route('mail', 'example@email.com')->notify($queueable), false),
    'custom service with method getter configured' => function () {
        config(['sidecar.queue.job_getters' => [Pay::class => 'getPayable']]);
        return new QueueTestHelper(new PassingPayable, fn ($queueable) => Pay::queue($queueable), false);
    },
    'custom service with attribute getter configured' => function () {
        config(['sidecar.queue.job_getters' => [Pay::class => 'payable']]);
        return new QueueTestHelper(new PassingPayable, fn ($queueable) => Pay::queue($queueable), false);
    },
];

$fails = [
    'failing job' => fn () => new QueueTestHelper(new FailingJob, fn ($queueable) => dispatch($queueable), false),
    // 'failing event' => fn () => new QueueTestHelper(new FailingEvent, fn ($queueable) => dispatch($queueable), false),
    // 'failing listener' => fn () => new QueueTestHelper(new FailingListener, fn ($queueable) => dispatch($queueable), false),
    'failing mailable' => fn () => new QueueTestHelper((new FailingMailable)->to('example@email.com'), fn ($queueable) => PendingMail::queue($queueable), false),
    'failing notification' => fn () => new QueueTestHelper(new FailingNotification, fn ($queueable) => Notification::route('mail', 'example@email.com')->notify($queueable), false),
    'failing custom service with method getter configured' => function () {
        config(['sidecar.queue.job_getters' => [Pay::class => 'getPayable']]);
        return new QueueTestHelper(new FailingPayable, fn ($queueable) => Pay::queue($queueable), false);
    },
    'failing custom service with attribute getter configured' => function () {
        config(['sidecar.queue.job_getters' => [Pay::class => 'payable']]);
        return new QueueTestHelper(new FailingPayable, fn ($queueable) => Pay::queue($queueable), false);
    },
];

$failsFromMaxAttempts = [
    'failing from max attempts job' => fn () => new QueueTestHelper(new AttemptsReachedJob, fn ($queueable) => dispatch($queueable), false),
    // 'failing from max attempts event' => fn () => new QueueTestHelper(new AttemptsReachedEvent, fn ($queueable) => dispatch($queueable), false),
    // 'failing from max attempts listener' => fn () => new QueueTestHelper(new AttemptsReachedListener, fn ($queueable) => dispatch($queueable), false),
    'failing from max attempts mailable' => fn () => new QueueTestHelper((new AttemptsReachedMailable)->to('example@email.com'), fn ($queueable) => PendingMail::queue($queueable), false),
    'failing from max attempts notification' => fn () => new QueueTestHelper(new AttemptsReachedNotification, fn ($queueable) => Notification::route('mail', 'example@email.com')->notify($queueable), false),
    'failing from max attempts custom service with method getter configured' => function () {
        config(['sidecar.queue.job_getters' => [PayAttemptsReached::class => 'getPayable']]);
        return new QueueTestHelper(new FailingPayable, fn ($queueable) => PayAttemptsReached::queue($queueable), false);
    },
    'failing from max attempts custom service with attribute getter configured' => function () {
        config(['sidecar.queue.job_getters' => [PayAttemptsReached::class => 'payable']]);
        return new QueueTestHelper(new FailingPayable, fn ($queueable) => PayAttemptsReached::queue($queueable), false);
    },
];

$release = [
    'releasing job' => fn () => new QueueTestHelper(new ReleasingJob, fn ($queueable) => dispatch($queueable), false),
    // 'releasing event' => fn () => new QueueTestHelper(new ReleasingEvent, fn ($queueable) => dispatch($queueable), false),
    // 'releasing listener' => fn () => new QueueTestHelper(new ReleasingListener, fn ($queueable) => dispatch($queueable), false),
    // 'releasing mailable' => fn () => new QueueTestHelper((new ReleasingMailable)->to('example@email.com'), fn ($queueable) => PendingMail::queue($queueable), false),
    // 'releasing notification' => fn () => new QueueTestHelper(new ReleasingNotification, fn ($queueable) => Notification::route('mail', 'example@email.com')->notify($queueable), false),
    // 'releasing custom service with method getter configured' => function () {
    //     config(['sidecar.queue.job_getters' => [Pay::class => 'getPayable']]);
    //     return new QueueTestHelper(new PassingPayable, fn ($queueable) => PayReleasing::queue($queueable), false);
    // },
    // 'releasing custom service with attribute getter configured' => function () {
    //     config(['sidecar.queue.job_getters' => [PayReleasing::class => 'payable']]);
    //     return new QueueTestHelper(new PassingPayable, fn ($queueable) => PayReleasing::queue($queueable), false);
    // },
];

$releaseWithDelay = [
    'releasing with delay job' => fn () => new QueueTestHelper(new ReleasingWithDelayJob, fn ($queueable) => dispatch($queueable), false),
    // 'releasing with delay event' => fn () => new QueueTestHelper(new ReleasingWithDelayEvent, fn ($queueable) => dispatch($queueable), false),
    // 'releasing with delay listener' => fn () => new QueueTestHelper(new ReleasingWithDelayListener, fn ($queueable) => dispatch($queueable), false),
    // 'releasing with delay mailable' => fn () => new QueueTestHelper((new ReleasingWithDelayMailable)->to('example@email.com'), fn ($queueable) => PendingMail::queue($queueable), false),
    // 'releasing with delay notification' => fn () => new QueueTestHelper(new ReleasingWithDelayNotification, fn ($queueable) => Notification::route('mail', 'example@email.com')->notify($queueable), false),
    // 'releasing with delay custom service with method getter configured' => function () {
    //     config(['sidecar.queue.job_getters' => [PayReleasingWithDelay::class => 'getPayable']]);
    //     return new QueueTestHelper(new PassingPayable, fn ($queueable) => PayReleasingWithDelay::queue($queueable), false);
    // },
    // 'releasing with delay custom service with attribute getter configured' => function () {
    //     config(['sidecar.queue.job_getters' => [PayReleasingWithDelay::class => 'payable']]);
    //     return new QueueTestHelper(new PassingPayable, fn ($queueable) => PayReleasingWithDelay::queue($queueable), false);
    // },
];

$optsIn = [
    'opted-in job' => fn () => new QueueTestHelper(new ImplementsRunInLambdaJob, fn ($queueable) => dispatch($queueable), false),
    // 'opted-in event' => fn () => new QueueTestHelper(new ImplementsRunInLambdaEvent, fn ($queueable) => dispatch($queueable), false),
    // 'opted-in listener' => fn () => new QueueTestHelper(new ImplementsRunInLambdaListener, fn ($queueable) => dispatch($queueable), false),
    'opted-in mailable' => fn () => new QueueTestHelper((new ImplementsRunInLambdaMailable)->to('example@email.com'), fn ($queueable) => PendingMail::queue($queueable), false),
    'opted-in notification' => fn () => new QueueTestHelper(new ImplementsRunInLambdaNotification, fn ($queueable) => Notification::route('mail', 'example@email.com')->notify($queueable), false),
    'opted-in custom service with method getter configured' => function () {
        config(['sidecar.queue.job_getters' => [Pay::class => 'getPayable']]);
        return new QueueTestHelper(new ImplementsRunInLambdaPayable, fn ($queueable) => Pay::queue($queueable), false);
    },
    'opted-in custom service with attribute getter configured' => function () {
        config(['sidecar.queue.job_getters' => [Pay::class => 'payable']]);
        return new QueueTestHelper(new ImplementsRunInLambdaPayable, fn ($queueable) => Pay::queue($queueable), false);
    },
];

$optsOut = [
    'opted-out job' => fn () => new QueueTestHelper(new ImplementsDoNotRunInLambdaJob, fn ($queueable) => dispatch($queueable), false),
    // 'opted-out event' => fn () => new QueueTestHelper(new ImplementsDoNotRunInLambdaEvent, fn ($queueable) => dispatch($queueable), false),
    // 'opted-out listener' => fn () => new QueueTestHelper(new ImplementsDoNotRunInLambdaListener, fn ($queueable) => dispatch($queueable), false),
    'opted-out mailable' => fn () => new QueueTestHelper((new ImplementsDoNotRunInLambdaMailable)->to('example@email.com'), fn ($queueable) => PendingMail::queue($queueable), false),
    'opted-out notification' => fn () => new QueueTestHelper(new ImplementsDoNotRunInLambdaNotification, fn ($queueable) => Notification::route('mail', 'example@email.com')->notify($queueable), false),
    'opted-out custom service with method getter configured' => function () {
        config(['sidecar.queue.job_getters' => [Pay::class => 'getPayable']]);
        return new QueueTestHelper(new ImplementsDoNotRunInLambdaPayable, fn ($queueable) => Pay::queue($queueable), false);
    },
    'opted-out custom service with attribute getter configured' => function () {
        config(['sidecar.queue.job_getters' => [Pay::class => 'payable']]);
        return new QueueTestHelper(new ImplementsDoNotRunInLambdaPayable, fn ($queueable) => Pay::queue($queueable), false);
    },
];

$optsInAndOut = [
    'opted-in and opted-out job' => fn () => new QueueTestHelper(new ImplementsBothRunInLambdaAndDoNotRunInLambdaJob, fn ($queueable) => dispatch($queueable), false),
    // 'opted-in and opted-out event' => fn () => new QueueTestHelper(new ImplementsBothRunInLambdaAndDoNotRunInLambdaEvent, fn ($queueable) => dispatch($queueable), false),
    // 'opted-in and opted-out listener' => fn () => new QueueTestHelper(new ImplementsBothRunInLambdaAndDoNotRunInLambdaListener, fn ($queueable) => dispatch($queueable), false),
    'opted-in and opted-out mailable' => fn () => new QueueTestHelper((new ImplementsBothRunInLambdaAndDoNotRunInLambdaMailable)->to('example@email.com'), fn ($queueable) => PendingMail::queue($queueable), false),
    'opted-in and opted-out notification' => fn () => new QueueTestHelper(new ImplementsBothRunInLambdaAndDoNotRunInLambdaNotification, fn ($queueable) => Notification::route('mail', 'example@email.com')->notify($queueable), false),
    'opted-in and opted-out custom service with method getter configured' => function () {
        config(['sidecar.queue.job_getters' => [Pay::class => 'getPayable']]);
        return new QueueTestHelper(new ImplementsBothRunInLambdaAndDoNotRunInLambdaPayable, fn ($queueable) => Pay::queue($queueable), false);
    },
    'opted-in and opted-out custom service with attribute getter configured' => function () {
        config(['sidecar.queue.job_getters' => [Pay::class => 'payable']]);
        return new QueueTestHelper(new ImplementsBothRunInLambdaAndDoNotRunInLambdaPayable, fn ($queueable) => Pay::queue($queueable), false);
    },
];

dataset('jobs', $succeeds);

dataset('jobs where each will succeed', $succeeds);
dataset('jobs where each will fail', $fails);
dataset('jobs where each will fail due to max attempts', $failsFromMaxAttempts);

dataset('jobs where each will delete', $succeeds);
dataset('jobs where each will release', $release);
dataset('jobs where each will release with delay', $releaseWithDelay);

dataset('jobs that implement nothing', $succeeds);
dataset('jobs that implement RunInLambda', $optsIn);
dataset('jobs that implement DoNotRunInLambda', $optsOut);
dataset('jobs that implement RunInLambda and DoNotRunInLambda', $optsInAndOut);
