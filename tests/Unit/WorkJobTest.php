<?php

namespace Hammerstone\Sidecar\PHP\Tests\Unit;

use Hammerstone\Sidecar\PHP\LaravelLambda;
use Hammerstone\Sidecar\PHP\Queue\LaravelLambdaWorker;
use Hammerstone\Sidecar\PHP\Tests\Support\QueueTestHelper;
use Hammerstone\Sidecar\PHP\Tests\Support\SidecarTestHelper;
use Hammerstone\Sidecar\Results\SettledResult;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Jobs\DatabaseJob;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    SidecarTestHelper::record()->enableQueueFeature(optin: true, queues: '*');
    app('events')->listen(JobProcessing::class, function (JobProcessing $event) {
        if ($event->job instanceof DatabaseJob) {
            $attempts = $event->job->payload()['attempts'] ?? $event->job->attempts();
            while ($event->job->attempts() < $attempts) {
                $event->job->getJobRecord()->increment();
            }
        }
    });
});

it('binds the extended queue worker when the sidecar queue feature is on', function () {
    SidecarTestHelper::record()->enableQueueFeature(optin: true, queues: '*');
    expect(app()->make('queue.worker'))->toBeInstanceOf(LaravelLambdaWorker::class);
});

it('does not bind the extended queue worker when the sidecar queue feature is off', function () {
    SidecarTestHelper::record()->disableQueueFeature();
    expect(app()->make('queue.worker'))->not->toBeInstanceOf(LaravelLambdaWorker::class);
});

it('does not run on lambda when the sidecar queue feature is off', function (QueueTestHelper $pendingJob) {
    SidecarTestHelper::record()->disableQueueFeature();
    $pendingJob->onQueue('lambda')->dispatch();
    $pendingJob->assertQueued();

    $pendingJob->runQueueWorker();

    $pendingJob->assertNotFailed();
    $pendingJob->assertNotQueued();
    $pendingJob->assertDeleted();
    $pendingJob->assertNotReleased();
    $pendingJob->assertNotExecutedOnLambda();
})->with('passed jobs');

it('can be opted-in and opted-out of running on lambda', function (QueueTestHelper $pendingJob, bool $mustOptIn, bool $optedInForLambdaExecution, bool $optedOutForLambdaExecution, bool $expected) {
    SidecarTestHelper::record()->enableQueueFeature(optin: $mustOptIn, queues: '*');
    $pendingJob->onQueue('lambda')->with([
        'optedInForLambdaExecution' => $optedInForLambdaExecution,
        'optedOutForLambdaExecution' => $optedOutForLambdaExecution,
    ])->dispatch();
    $pendingJob->assertQueued();

    $pendingJob->runQueueWorker();

    $pendingJob->assertNotFailed();
    $pendingJob->assertNotQueued();
    $pendingJob->assertDeleted();
    $pendingJob->assertNotReleased();
    $pendingJob->assertExecutedOnLambda($expected ? 1 : 0);
})->with('passed jobs')->with([
    'must-opt-in + in + out' => [true, true, true, false],
    'must-opt-in + !in + out' => [true, false, true, false],
    'must-opt-in + in + !out' => [true, true, false, true],
    'must-opt-in + !in + !out' => [true, false, false, false],
    '!must-opt-in + in + out' => [false, true, true, false],
    '!must-opt-in + !in + out' => [false, false, true, false],
    '!must-opt-in + in + !out' => [false, true, false, true],
    '!must-opt-in + !in + !out' => [false, false, false, true],
]);

it('can pass', function (QueueTestHelper $pendingJob) {
    SidecarTestHelper::record()->enableQueueFeature(optin: false, queues: '*');
    $pendingJob->onQueue('lambda')->dispatch();
    $pendingJob->assertQueued();

    $pendingJob->runQueueWorker();

    $pendingJob->assertNotFailed();
    $pendingJob->assertNotQueued();
    $pendingJob->assertNotReleased();
    $pendingJob->assertNotDelayed();
    $pendingJob->assertExecutedOnLambda(1, function (SettledResult $result) {
        expect(Arr::only($result->body(), ['failed', 'released', 'delay']))->toBe([
            'failed' => false,
            'released' => false,
            'delay' => 0,
        ]);
    });
})->with('passed jobs');

it('can release jobs', function (QueueTestHelper $pendingJob) {
    SidecarTestHelper::record()->enableQueueFeature(optin: false, queues: '*');
    $pendingJob->onQueue('lambda')->dispatch();
    $pendingJob->assertQueued();

    $pendingJob->runQueueWorker();

    $pendingJob->assertNotFailed();
    $pendingJob->assertQueued();
    $pendingJob->assertReleased();
    $pendingJob->assertNotDelayed();
    $pendingJob->assertExecutedOnLambda(1, function (SettledResult $result) {
        expect(Arr::only($result->body(), ['released', 'delay']))->toBe([
            'released' => true,
            'delay' => 0,
        ]);
    });
})->with('released jobs');

it('does not release the job within the lambda', function (QueueTestHelper $pendingJob) {
    SidecarTestHelper::record()
        ->enableQueueFeature(optin: false, queues: '*')
        ->transform(LaravelLambda::class, function (array $body) {
            // Why are we using releasing examples? Because the worker should be managing the queue, not the lambda, therefore the worker will receive these returned values.
            expect($body['released'])->toBe(true);
            return [...$body, 'released' => false];
        });
    $pendingJob->onQueue('lambda')->dispatch();
    $pendingJob->assertQueued();

    $pendingJob->runQueueWorker();

    $pendingJob->assertNotFailed();
    $pendingJob->assertNotQueued();
    $pendingJob->assertDeleted();
    $pendingJob->assertNotReleased();
    $pendingJob->assertExecutedOnLambda(1, function (SettledResult $result) {
        expect(Arr::only($result->body(), ['released', 'delay']))->toBe([
            'released' => false,
            'delay' => 0,
        ]);
    });
})->with('released jobs');

it('can release jobs with delay', function (QueueTestHelper $pendingJob) {
    SidecarTestHelper::record()
        ->enableQueueFeature(optin: false, queues: '*')
        ->transform(LaravelLambda::class, function (array $body) {
            // Why are we changing the delay? Because the worker should be managing the queue, not the lambda, therefore the worker will receive these returned values.
            expect($body['delay'])->toBe(10);
            return [...$body, 'delay' => 27];
        });
    $pendingJob->onQueue('lambda')->dispatch();
    $pendingJob->assertQueued();

    $pendingJob->runQueueWorker();

    $pendingJob->assertNotFailed();
    $pendingJob->assertQueued();
    $pendingJob->assertReleased();
    $pendingJob->assertDelayed(27);
    $pendingJob->assertExecutedOnLambda(1, function (SettledResult $result) {
        expect(Arr::only($result->body(), ['released', 'delay']))->toBe([
            'released' => true,
            'delay' => 27,
        ]);
    });
})->with('released jobs with delay');

it('can fail and release for retry [fail]', function (QueueTestHelper $pendingJob) {
    SidecarTestHelper::record()->enableQueueFeature(optin: false, queues: '*');
    $pendingJob->onQueue('lambda')->with([
        'attempts' => 1,
        'maxTries' => 3,
    ])->dispatch();
    $pendingJob->assertQueued();

    $pendingJob->runQueueWorker();

    $pendingJob->assertFailed();
    $pendingJob->assertNotQueued();
    $pendingJob->assertNotReleased();
    $pendingJob->assertNotDelayed();
    $pendingJob->assertExecutedOnLambda(1, function (SettledResult $result) {
        expect(Arr::only($result->body(), ['failed', 'released', 'delay']))->toBe([
            'failed' => true, // The lambda flagged it as failed.
            'released' => false,
            'delay' => 0,
        ]);

        $exception = unserialize($result->body()['exception']);
        expect($exception)->toBeNull();
    });
})->with('failed jobs');

it('can fail and release for retry [exception]', function (QueueTestHelper $pendingJob) {
    SidecarTestHelper::record()->enableQueueFeature(optin: false, queues: '*');
    $pendingJob->onQueue('lambda')->with([
        'attempts' => 1,
        'maxTries' => 3,
    ])->dispatch();
    $pendingJob->assertQueued();

    $pendingJob->runQueueWorker();

    $pendingJob->assertNotFailed();
    $pendingJob->assertQueued(); // Due to retry.
    $pendingJob->assertReleased(); // Due to retry.
    $pendingJob->assertNotDelayed();
    $pendingJob->assertTries(2);
    $pendingJob->assertMaxTries(3);
    $pendingJob->assertTriesRemaining(1);
    $pendingJob->assertExecutedOnLambda(1, function (SettledResult $result) {
        expect(Arr::only($result->body(), ['failed', 'released', 'delay']))->toBe([
            'failed' => false, // Didn't fail because the lambda flagged it to.
            'released' => false, // Didn't release because the lambda flagged it to.
            'delay' => 0,
        ]);

        $exception = unserialize($result->body()['exception']);
        expect($exception)->not->toBeNull();
        expect($exception->getMessage())->toBeIn([
            "You're a terrible stuntman.",
            "I'm just kidding. I could hear you. It was just really mean.",
            "They've done it! They've raised $50,000 for Frank's conveniently priced surgery!",
        ]);
    });
})->with('thrown jobs');

it('can fail when the max attempts is hit [fail]', function (QueueTestHelper $pendingJob) {
    SidecarTestHelper::record()->enableQueueFeature(optin: false, queues: '*');
    $pendingJob->onQueue('lambda')->with([
        'attempts' => 3,
        'maxTries' => 3,
    ])->dispatch();
    $pendingJob->assertQueued();

    $pendingJob->runQueueWorker();

    $pendingJob->assertFailed();
    $pendingJob->assertNotQueued();
    $pendingJob->assertDeleted();
    $pendingJob->assertNotReleased();
    $pendingJob->assertNotDelayed();
    $pendingJob->assertTries(3);
    $pendingJob->assertMaxTries(3);
    $pendingJob->assertTriesRemaining(0);
    $pendingJob->assertExecutedOnLambda(1, function (SettledResult $result) {
        expect(Arr::only($result->body(), ['failed', 'released', 'delay']))->toBe([
            'failed' => true,
            'released' => false,
            'delay' => 0,
        ]);
    });
})->with('failed jobs');

it('can fail when the max attempts is hit [exception]', function (QueueTestHelper $pendingJob) {
    SidecarTestHelper::record()->enableQueueFeature(optin: false, queues: '*');
    $pendingJob->onQueue('lambda')->with([
        'attempts' => 3,
        'maxTries' => 3,
    ])->dispatch();
    $pendingJob->assertQueued();

    $pendingJob->runQueueWorker();

    $pendingJob->assertFailed();
    $pendingJob->assertNotQueued();
    $pendingJob->assertDeleted();
    $pendingJob->assertNotReleased();
    $pendingJob->assertNotDelayed();
    $pendingJob->assertTries(3);
    $pendingJob->assertMaxTries(3);
    $pendingJob->assertTriesRemaining(0);
    $pendingJob->assertExecutedOnLambda(1, function (SettledResult $result) {
        expect(Arr::only($result->body(), ['failed', 'released', 'delay']))->toBe([
            'failed' => false,
            'released' => false,
            'delay' => 0,
        ]);
    });
})->with('thrown jobs');
