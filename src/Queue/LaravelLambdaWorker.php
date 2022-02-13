<?php

namespace Hammerstone\Sidecar\PHP\Queue;

use Closure;
use Hammerstone\Sidecar\PHP\LaravelLambda;
use Hammerstone\Sidecar\PHP\Support\Decorator;
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

                    [$class, $method] = JobName::parse($payload['job']);
                    $data = $payload['data'];
                    $queue = $this->queue;
                    $connectionName = $this->connectionName;
                    $this->instance = $this->resolve($class);

                    $result = LaravelLambda::execute(function () use ($class, $method, $data, $queue, $payload, $connectionName) {
                        $job = app()->makeWith(SyncJob::class, [
                            'queue' => $queue,
                            'payload' => $payload,
                            'connectionName' => $connectionName,
                        ]);

                        app()->make($class)->{$method}($job, $data);

                        return [
                            'deleted' => $job->isDeleted(),
                            'released' => $job->isReleased(),
                            'delay' => $job->getReleaseDelay(),
                        ];
                    })->throw()->body();

                    if ($result['deleted']) {
                        $this->delete();
                    }

                    if ($result['released']) {
                        $this->release($result['delay']);
                    }
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
