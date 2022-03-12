<?php

namespace Hammerstone\Sidecar\PHP\Queue;

use Closure;
use Hammerstone\Sidecar\PHP\Events\LambdaJobProcessed;
use Hammerstone\Sidecar\PHP\Events\LambdaJobProcessing;
use Hammerstone\Sidecar\PHP\LaravelLambda;
use Hammerstone\Sidecar\PHP\Support\Decorator;
use Illuminate\Queue\Jobs\JobName;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Queue\Worker;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Arr;
use ReflectionClass;
use ReflectionFunction;
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

                    $payload['id'] ??= $this->getJobId();
                    $payload['attempts'] ??= $this->attempts();
                    $payload['maxTries'] ??= $this->maxTries();
                    $payload['maxExceptions'] ??= $this->maxExceptions();
                    $payload['backoff'] ??= $this->backoff();
                    $payload['timeout'] ??= $this->timeout();
                    $payload['retryUntil'] ??= $this->retryUntil();
                    $payload['failOnTimeout'] ??= $this->shouldFailOnTimeout();

                    $result = LaravelLambda::execute(function () use ($class, $method, $data, $queue, $payload, $connectionName) {
                        $exception = null;
                        $container = app();
                        $rawPayload = json_encode($payload);
                        $job = new class($container, $rawPayload, $connectionName, $queue) extends SyncJob {
                            public $delay = 0;

                            public function release($delay = 0)
                            {
                                parent::release($this->delay = $delay);
                            }

                            public function attempts()
                            {
                                return $this->payload()['attempts'] ?? 1;
                            }

                            public function getJobId()
                            {
                                return $this->payload()['id'] ?? null;
                            }
                        };

                        // TODO: set drivers to collect dispatches and logs for the response.

                        event(new LambdaJobProcessing($connectionName, $job));

                        try {
                            $container->make($class)->{$method}($job, $data);
                        } catch (Throwable $error) {
                            $cursor = $error;
                            $prop = tap((new ReflectionClass($error::class))->getProperty('trace'))->setAccessible(true);
                            do {
                                $trace = $prop->getValue($cursor);
                                foreach ($trace as &$call) {
                                    array_walk_recursive($call['args'], function (&$value, $key) {
                                        if ($value instanceof Closure) {
                                            $reflection = new ReflectionFunction($value);
                                            $value = sprintf('Closure(%s:%s)', $reflection->getFileName(), $reflection->getStartLine());
                                        } elseif (is_object($value)) {
                                            $value = sprintf('Object(%s)', get_class($value));
                                        } elseif (is_resource($value)) {
                                            $value = sprintf('Resource(%s)', get_resource_type($value));
                                        }
                                    });
                                }
                                $prop->setValue($cursor, $trace);
                            } while ($cursor = $cursor->getPrevious());
                            $prop->setAccessible(false);
                            $exception = $error;
                        }

                        return tap([
                            'failed' => $job->hasFailed(),
                            'exception' => serialize($exception),
                            'released' => $job->isReleased(),
                            'delay' => (int) $job->delay,
                            // TODO: jobs to dispatch. Note that release() did not dispatch anything.
                            // TODO: logs to log. This should be done by the queue manager because a Forge box likely would have a log file vs. cloudwatch logs.
                        ], fn () => event(new LambdaJobProcessed($connectionName, $job)));
                    })->throw()->body();

                    $exception = unserialize($result['exception']);

                    // Pushes to failed_jobs and deletes the current job.
                    if ($result['failed']) {
                        return $this->fail($exception);
                    }

                    // Worker will release for retry, or mark as failed for reaching either max attempts or max exceptions.
                    if ($exception) {
                        throw $exception;
                    }

                    // Dispatches the job for another attempt and deletes the current job.
                    if ($result['released']) {
                        return $this->release($result['delay']);
                    }

                    // Deletes the current job.
                    $this->delete();
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
