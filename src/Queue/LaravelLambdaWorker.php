<?php

namespace Hammerstone\Sidecar\PHP\Queue;

use Hammerstone\Sidecar\PHP\LaravelLambda;
use Hammerstone\Sidecar\PHP\Support\Decorator;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Notifications\SendQueuedNotifications;
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
                $job = $this->getDecorated();

                $enabled = config('sidecar.queue.enabled', false);
                $optInRequired = config('sidecar.queue.opt_in_required', true);
                $optedIn = Arr::get($job->payload(), 'optedInForLambdaExecution', false);
                $optedOut = Arr::get($job->payload(), 'optedOutForLambdaExecution', false);

                if (! $enabled) {
                    return $job->fire();
                }

                if ($optedOut) {
                    return $job->fire();
                }

                if ($optInRequired && ! $optedIn) {
                    return $job->fire();
                }

                LaravelLambda::execute(fn () => $job->fire())->throw();
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
