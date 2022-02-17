<?php

namespace Hammerstone\Sidecar\PHP\Queue;

use Hammerstone\Sidecar\PHP\LaravelLambda;
use Hammerstone\Sidecar\PHP\Support\Decorator;
use Illuminate\Queue\Jobs\JobName;
use Illuminate\Queue\Jobs\SyncJob;
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
                $this->invade(function () {
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
                        $container = app();
                        $job = new class ($container, $payload, $connectionName, $queue) extends SyncJob {
                            public $delay = 0;

                            public function release($delay = 0)
                            {
                                parent::release($this->delay = $delay);
                            }
                        };
                        // TODO: set drivers to collect dispatches, failed jobs, and logs for the response.

                        $container->make($class)->{$method}($job, $data);

                        return [
                            'deleted' => $job->isDeleted(),
                            'released' => $job->isReleased(),
                            'delay' => (int) $job->delay,
                            // TODO: jobs to dispatch. Note that deleted and released did not dispatch anything.
                            // TODO: failed jobs to add, might be worth having this execute locally to check that failed jobs are done by the queue manager?
                            // TODO: logs to log. This should be done by the queue manager because a Forge box likely would have a log file vs. cloudwatch logs.
                        ];
                    })->throw()->body();

                    if ($result['deleted']) {
                        $this->delete();
                    }

                    if ($result['released']) {
                        $this->release($result['delay']);
                    }

                    // TODO: dispatch the returned jobs
                    // TODO: log the returned failed jobs
                    // TODO: log the returned logs
                });
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
