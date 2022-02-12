<?php
/**
 * @author Aaron Francis <aaron@hammerstone.dev>
 */

namespace Hammerstone\Sidecar\PHP\Providers;

use Hammerstone\Sidecar\PHP\Commands\FetchVaporLayers;
use Hammerstone\Sidecar\PHP\Queue\Workers\LaravelLambdaWorker;
use Illuminate\Queue\Worker;
use Illuminate\Support\ServiceProvider;

class SidecarPhpServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            FetchVaporLayers::class,
        ]);

        if (config('sidecar.queue.enabled', false)) {
            $this->app->bind(Worker::class, LaravelLambdaWorker::class);
        }
    }
}
