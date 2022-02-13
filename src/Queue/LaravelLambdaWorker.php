<?php

namespace Hammerstone\Sidecar\PHP\Queue;

use Closure;
use Hammerstone\Sidecar\PHP\LaravelLambda;
use Hammerstone\Sidecar\PHP\Support\Decorator;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Queue\Jobs\JobName;
use Illuminate\Queue\Worker;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Arr;
use Throwable;

class LaravelLambdaWorker extends Worker
{
    public function process($connectionName, $job, WorkerOptions $options)
    {
        $lambdaJob = new class ($job) extends Decorator {
            public function fire()
            {
                $this->invade($this->getDecorated(), function () {
                    $payload = $this->payload();

                    $enabled = config('sidecar.queue.enabled', false);
                    $optInRequired = config('sidecar.queue.opt_in_required', true);
                    $optedIn = Arr::get($payload, 'optedInForLambdaExecution', false);
                    $optedOut = Arr::get($payload, 'optedOutForLambdaExecution', false);

                    if (! $enabled) {
                        return $this->fire();
                    }

                    if ($optedOut) {
                        return $this->fire();
                    }

                    if ($optInRequired && ! $optedIn) {
                        return $this->fire();
                    }

                    // The \Illuminate\Queue\Jobs\Job instance wasn't serialising to the closure.
                    // We can new up another instance with these primitive params covering all queue driver constructors.
                    $jobClass = $this::class;
                    $jobParams = [
                        'job' => $this->job ?? null,
                        'reserved' => $this->reserved ?? null,
                        'connectionName' => $this->connectionName ?? null,
                        'queue' => $this->queue ?? null,
                        'payload' => $this->payload ?? null,
                    ];
                    $data = $payload['data'];
                    [$class, $method] = JobName::parse($payload['job']);

                    // Set the resolved instance the same as the original fire method.
                    $this->instance = $this->resolve($class);

                    LaravelLambda::execute(function () use ($class, $method, $data, $jobClass, $jobParams) {
                        $job = app()->makeWith($jobClass, $jobParams);
                        app()->make($class)->{$method}($job, $data);
                    })->throw();
                });
            }

            private function invade(object $subject, Closure $callback)
            {
                return Closure::bind($callback, $subject, $subject::class)();
            }
        };

        parent::process($connectionName, $lambdaJob, $options);
    }

    protected function raiseBeforeJobEvent($connectionName, $job)
    {
        parent::raiseBeforeJobEvent($connectionName, $job->getDecorated());
    }

    protected function markJobAsFailedIfAlreadyExceedsMaxAttempts($connectionName, $job, $maxTries)
    {
        parent::markJobAsFailedIfAlreadyExceedsMaxAttempts($connectionName, $job->getDecorated(), $maxTries);
    }

    protected function raiseAfterJobEvent($connectionName, $job)
    {
        parent::raiseAfterJobEvent($connectionName, $job->getDecorated());
    }

    protected function handleJobException($connectionName, $job, WorkerOptions $options, Throwable $e)
    {
        parent::handleJobException($connectionName, $job->getDecorated(), $options, $e);
    }
}
