<?php
/**
 * @author Aaron Francis <aaron@hammerstone.dev>
 */

namespace Hammerstone\Sidecar\PHP\Providers;

use Closure;
use Hammerstone\Sidecar\PHP\Commands\FetchVaporLayers;
use Hammerstone\Sidecar\PHP\Contracts\Queue\DoNotRunInLambda;
use Hammerstone\Sidecar\PHP\Contracts\Queue\RunInLambda;
use Hammerstone\Sidecar\PHP\Queue\LaravelLambdaWorker;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Queue\Console\WorkCommand;
use Illuminate\Queue\Worker;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use ReflectionClass;

class SidecarPhpServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->registerCommands();
        }

        $this->listenForJobsBeingQueued()
            ->registerLambdaQueueWorker();
    }

    protected function registerCommands()
    {
        $this->commands([
            FetchVaporLayers::class,
        ]);
    }

    protected function listenForJobsBeingQueued()
    {
        $this->app['queue']->createPayloadUsing(function ($connectionName, $queue, $payload) {
            $queue = Str::after($queue, 'queues:');
            $queueable = $payload['data']['command'];

            $allowedQueues = Collection::wrap(config('sidecar.queue.allowed_queues', '*'))->map(fn ($queue) => Str::after($queue, 'queues:'));
            $queueIsAllowed = $allowedQueues->contains('*') || $allowedQueues->contains($queue);

            $optedIn = (new ReflectionClass($this->getJobFromQueueable($queueable)))->implementsInterface(RunInLambda::class);
            $optedOut = (new ReflectionClass($this->getJobFromQueueable($queueable)))->implementsInterface(DoNotRunInLambda::class);

            return [
                'optedInForLambdaExecution' => $optedIn,
                'optedOutForLambdaExecution' => $optedOut || $queueIsAllowed === false,
            ];
        });

        return $this;
    }

    protected function registerLambdaQueueWorker(): self
    {
        $worker = $this->app->make('queue.worker');
        $isLambaQueueWorkerTurnedOff = ! config('sidecar.queue.enabled', false);

        $this->app->singleton('queue.worker', fn ($app) => $isLambaQueueWorkerTurnedOff ? $worker : new LaravelLambdaWorker(
            $this->invade($worker, fn () => $this->manager),
            $this->invade($worker, fn () => $this->events),
            $this->invade($worker, fn () => $this->exceptions),
            $this->invade($worker, fn () => $this->isDownForMaintenance),
            $this->invade($worker, fn () => $this->resetScope),
        ));

        return $this;
    }

    private function invade(object $subject, Closure $callback)
    {
        return Closure::bind($callback, $subject, $subject::class)();
    }

    private function getJobFromQueueable(object $queueable)
    {
        $jobGetter = Arr::get([
            BroadcastEvent::class => 'event',
            CallQueuedListener::class => 'class',
            SendQueuedMailable::class => 'mailable',
            SendQueuedNotifications::class => 'notification',
            ...config('sidecar.queue.job_getters', []),
        ], $queueable::class);

        if (! $jobGetter) {
            return $queueable;
        }

        return method_exists($queueable, $jobGetter)
            ? $queueable->{$jobGetter}()
            : $queueable->{$jobGetter};
    }
}
