<?php

namespace Hammerstone\Sidecar\PHP\Tests\Unit;

use Hammerstone\Sidecar\PHP\Queue\LaravelLambdaWorker;
use Hammerstone\Sidecar\PHP\Tests\Support\QueueTestHelper;
use Hammerstone\Sidecar\PHP\Tests\Support\SidecarTestHelper;
use Hammerstone\Sidecar\Results\SettledResult;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    SidecarTestHelper::record()->enableQueueFeature(optin: true, queues: '*');
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
})->with('jobs where each will succeed');

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
})->with('jobs where each will succeed')->with([
    'must-opt-in + in + out' => [true, true, true, false],
    'must-opt-in + !in + out' => [true, false, true, false],
    'must-opt-in + in + !out' => [true, true, false, true],
    'must-opt-in + !in + !out' => [true, false, false, false],
    '!must-opt-in + in + out' => [false, true, true, false],
    '!must-opt-in + !in + out' => [false, false, true, false],
    '!must-opt-in + in + !out' => [false, true, false, true],
    '!must-opt-in + !in + !out' => [false, false, false, true],
]);

test('when the lambda flags its job as deleted then the job is deleted', function (QueueTestHelper $pendingJob) {
    SidecarTestHelper::record()->enableQueueFeature(optin: false, queues: '*');
    $pendingJob->onQueue('lambda')->dispatch();
    $pendingJob->assertQueued();

    $pendingJob->runQueueWorker();

    $pendingJob->assertNotFailed();
    $pendingJob->assertNotQueued();
    $pendingJob->assertDeleted();
    $pendingJob->assertNotReleased();
    $pendingJob->assertExecutedOnLambda(1, function (SettledResult $result) {
        expect(Arr::only($result->body(), ['deleted', 'released', 'delay']))->toBe([
            'deleted' => true,
            'released' => false,
            'delay' => 0,
        ]);
    });
})->with('jobs where each will delete');

test('when the lambda flags its job as not deleted then the job is not deleted', function (QueueTestHelper $pendingJob) {
    // GIVEN: a queued job that will run on lambda and not delete and not release
    SidecarTestHelper::record()
        ->enableQueueFeature(optin: false, queues: '*')
        ->transform(function (array $body) {
            // Why are we using releasing examples? Because the worker should be managing the queue, not the lambda, therefore the worker will receive these returned values.
            expect($body['deleted'])->toBe(true);
            expect($body['released'])->toBe(true);
            return [...$body, 'deleted' => false, 'released' => false];
        });
    $pendingJob->onQueue('lambda')->dispatch();
    $pendingJob->assertQueued();

    // WHEN: we run the queue worker
    $pendingJob->runQueueWorker();

    // THEN: the job is not deleted and was not released either.
    $pendingJob->assertNotFailed();
    $pendingJob->assertQueued(1);
    $pendingJob->assertNotDeleted();
    $pendingJob->assertNotReleased();
    $pendingJob->assertExecutedOnLambda(1, function (SettledResult $result) {
        expect(Arr::only($result->body(), ['deleted', 'released', 'delay']))->toBe([
            'deleted' => false,
            'released' => false,
            'delay' => 0,
        ]);
    });
    // Can the QueueTestHelper pop the job off of the queue so we can assert on the payload?

    // lets do a quick little reset of the queue and sidecar
    SidecarTestHelper::reset();
    $pendingJob->clearQueue();
    $pendingJob->assertNotFailed();
    $pendingJob->assertNotQueued();
    $pendingJob->assertNotDeleted();
    $pendingJob->assertNotReleased();
    $pendingJob->assertNotExecutedOnLambda();

    // GIVEN: a queued job that will run on lambda and not delete, but this time it will release (which is not normal but, hey)
    SidecarTestHelper::record()
        ->enableQueueFeature(optin: false, queues: '*')
        ->transform(function (array $body) {
            // Don't worry, this doesn't happen, we're asserting on seperate responsibilities is all. Also, the lambda should not be managing the queue.
            expect($body['deleted'])->toBe(true);
            expect($body['released'])->toBe(true);
            return [...$body, 'deleted' => false, 'released' => true];
        });
    $pendingJob->onQueue('lambda')->dispatch();
    $pendingJob->assertQueued();

    // WHEN: we run the queue worker
    $pendingJob->runQueueWorker();

    // THEN: the job is not deleted but was also released resulting in two jobs queued.
    $pendingJob->assertNotFailed();
    $pendingJob->assertQueued(2);
    $pendingJob->assertNotDeleted();
    $pendingJob->assertReleased();
    $pendingJob->assertNotDelayed();
    $pendingJob->assertExecutedOnLambda(1, function (SettledResult $result) {
        expect(Arr::only($result->body(), ['deleted', 'released', 'delay']))->toBe([
            'deleted' => false,
            'released' => true,
            'delay' => 0,
        ]);
    });
    // Can the QueueTestHelper pop the job off of the queue so we can assert on the payload?
})->with('jobs where each will release');

test('when the lambda flags its job as released then the job is released', function (QueueTestHelper $pendingJob) {
    SidecarTestHelper::record()->enableQueueFeature(optin: false, queues: '*');
    $pendingJob->onQueue('lambda')->dispatch();
    $pendingJob->assertQueued();

    $pendingJob->runQueueWorker();

    $pendingJob->assertNotFailed();
    $pendingJob->assertQueued();
    $pendingJob->assertDeleted();
    $pendingJob->assertReleased();
    $pendingJob->assertNotDelayed();
    $pendingJob->assertExecutedOnLambda(1, function (SettledResult $result) {
        expect(Arr::only($result->body(), ['deleted', 'released', 'delay']))->toBe([
            'deleted' => true,
            'released' => true,
            'delay' => 0,
        ]);
    });
})->with('jobs where each will release');

test('when the lambda flags its job as not released then the job is not released', function (QueueTestHelper $pendingJob) {
    SidecarTestHelper::record()->enableQueueFeature(optin: false, queues: '*')->transform(function (array $body) {
        // Why are we using releasing examples? Because the worker should be managing the queue, not the lambda, therefore the worker will receive these returned values.
        expect($body['deleted'])->toBe(true);
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
        expect(Arr::only($result->body(), ['deleted', 'released', 'delay']))->toBe([
            'deleted' => true,
            'released' => false,
            'delay' => 0,
        ]);
    });
})->with('jobs where each will release');

test('when the lambda flags its job as released with a custom delay then the job is released with the same custom delay', function (QueueTestHelper $pendingJob) {
    SidecarTestHelper::record()->enableQueueFeature(optin: false, queues: '*')->transform(function (array $body) {
        // Why are we changing the delay? Because the worker should be managing the queue, not the lambda, therefore the worker will receive these returned values.
        expect($body['delay'])->toBe(10);
        return [...$body, 'delay' => 27];
    });
    $pendingJob->onQueue('lambda')->dispatch();
    $pendingJob->assertQueued();

    $pendingJob->runQueueWorker();

    $pendingJob->assertNotFailed();
    $pendingJob->assertQueued();
    $pendingJob->assertDeleted();
    $pendingJob->assertReleased();
    $pendingJob->assertDelayed(27);
    $pendingJob->assertExecutedOnLambda(1, function (SettledResult $result) {
        expect(Arr::only($result->body(), ['deleted', 'released', 'delay']))->toBe([
            'deleted' => true,
            'released' => true,
            'delay' => 27,
        ]);
    });
})->with('jobs where each will release with delay');

test('when the lambda throws an exception then the job is marked as failed and released for retry', function (QueueTestHelper $pendingJob) {
    SidecarTestHelper::record()->enableQueueFeature(optin: false, queues: '*');
    $pendingJob->onQueue('lambda')->with([
        'tries' => 1,
        'maxTries' => 3,
    ])->dispatch();
    $pendingJob->assertQueued();

    $pendingJob->runQueueWorker();

    $pendingJob->assertNotFailed();
    $pendingJob->assertQueued();
    $pendingJob->assertDeleted();
    $pendingJob->assertReleased();
    $pendingJob->assertNotDelayed();
    $pendingJob->assertTries(2);
    $pendingJob->assertMaxTries(3);
    $pendingJob->assertTriesRemaining(1);
    $pendingJob->assertExecutedOnLambda(1, function (SettledResult $result) {
        expect(Arr::only($result->body(), ['deleted', 'released', 'delay']))->toBe([
            'deleted' => true,
            'released' => true,
            'delay' => 0,
        ]);
    });
})->with('jobs where each will fail');

test('it will not run on lambda when the job has hit its max attempts and the job will fail as normal with data set "failing from max attempts job"', function (QueueTestHelper $pendingJob) {
    \Hammerstone\Sidecar\PHP\Support\Config\SidecarConfig::make()->queueDriverSupported();
    SidecarTestHelper::record()->enableQueueFeature(optin: false, queues: '*');
    $pendingJob->onQueue('lambda')->with([
        'tries' => 2,
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
        expect(Arr::only($result->body(), ['deleted', 'released', 'delay']))->toBe([
            'deleted' => true,
            'released' => false,
            'delay' => 0,
        ]);
    });
})->with('jobs where each will fail due to max attempts');
