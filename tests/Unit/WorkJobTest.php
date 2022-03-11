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

it('will bind the extended queue worker when the sidebar queues feature is on', function () {
    SidecarTestHelper::record()->enableQueueFeature(optin: true, queues: '*');
    expect(app()->make('queue.worker'))->toBeInstanceOf(LaravelLambdaWorker::class);
});

it('will not bind the extended queue worker when the sidebar queues feature is off', function () {
    SidecarTestHelper::record()->disableQueueFeature();
    expect(app()->make('queue.worker'))->not->toBeInstanceOf(LaravelLambdaWorker::class);
});

it('will not run on lambda when the sidebar queues feature is off', function (QueueTestHelper $pendingJob) {
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

it('will run on lambda in the following payload opted-in/opted-out conditions', function (QueueTestHelper $pendingJob, bool $mustOptIn, bool $optedInForLambdaExecution, bool $optedOutForLambdaExecution, bool $expected) {
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

test('when the lambda flags its job as released then the job is released', function (QueueTestHelper $pendingJob) {
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

test('when the lambda flags its job as not released then the job is not released', function (QueueTestHelper $pendingJob) {
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

test('when the lambda flags its job as released with a custom delay then the job is released with the same custom delay', function (QueueTestHelper $pendingJob) {
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

test('when the lambda flags its job as failed then it is pushed to the failed jobs and not released for retry', function (QueueTestHelper $pendingJob) {
    SidecarTestHelper::record()->enableQueueFeature(optin: false, queues: '*');
    $pendingJob->onQueue('lambda')->with([
        'attempts' => 1,
        'maxTries' => 1,
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

test('when the lambda throws an exception then the job is not marked as failed and released for retry', function (QueueTestHelper $pendingJob) {
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

test('it will not run on lambda when the job has hit its max attempts (from marked as failed) and the job will fail as normal', function (QueueTestHelper $pendingJob) {
    \Hammerstone\Sidecar\PHP\Support\Config\SidecarConfig::make()->queueDriverSupported();
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

test('it will not run on lambda when the job has hit its max attempts (from thrown exception) and the job will fail as normal', function (QueueTestHelper $pendingJob) {
    \Hammerstone\Sidecar\PHP\Support\Config\SidecarConfig::make()->queueDriverSupported();
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
