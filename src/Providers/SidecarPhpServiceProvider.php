<?php
/**
 * @author Aaron Francis <aaron@hammerstone.dev>
 */

namespace Hammerstone\Sidecar\PHP\Providers;

use Hammerstone\Sidecar\Clients\CloudWatchLogsClient;
use Hammerstone\Sidecar\Clients\LambdaClient;
use Hammerstone\Sidecar\Commands\Activate;
use Hammerstone\Sidecar\Commands\Configure;
use Hammerstone\Sidecar\Commands\Deploy;
use Hammerstone\Sidecar\Commands\Install;
use Hammerstone\Sidecar\Commands\Warm;
use Hammerstone\Sidecar\Manager;
use Hammerstone\Sidecar\Package;
use Hammerstone\Sidecar\PHP\Commands\FetchVaporLayers;
use Hammerstone\Sidecar\PHP\Commands\VaporLayers;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;

class SidecarPhpServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                FetchVaporLayers::class,
            ]);
        }
    }
}
