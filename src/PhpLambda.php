<?php
/**
 * @author Aaron Francis <aarondfrancis@gmail.com|https://twitter.com/aarondfrancis>
 */

namespace Hammerstone\Sidecar\PHP;

use Hammerstone\Sidecar\LambdaFunction;
use Hammerstone\Sidecar\Region;
use Hammerstone\Sidecar\Runtime;
use Laravel\SerializableClosure\SerializableClosure;

class PhpLambda extends LambdaFunction
{
    public function name()
    {
        return 'PHP-Lambda';
    }

    public function handler()
    {
        // Because we control the runtime, there is no
        // concept of a handler for this function.
        return 'noop';
    }

    public function timeout()
    {
        return 5;
    }

    public function runtime()
    {
        // Custom Alpine Linux runtime. The Vapor layer provides
        // the PHP, and we will provide the event loop.
        return Runtime::PROVIDED_AL2;
    }

    public function package()
    {
        return Package::make()->withVanillaPhp();
    }

    public function layers()
    {
        return [
            // Add the Vapor-provided PHP layer.
            VaporLayers::find(),
        ];
    }

    public function preparePayload($payload)
    {
        if ($this->shouldResetClosureBinding()) {
            $payload = $payload->bindTo(null, null);
        }

        return [
            'closure' => base64_encode(serialize(new SerializableClosure($payload)))
        ];
    }

    public function shouldResetClosureBinding()
    {
        // Since we don't ship the entire application, make the
        // closure fully anonymous by resetting the scope.
        return true;
    }

    public function variables()
    {
        return [
            // These two variables are required for the Vapor runtime to work properly.
            'PATH' => '/opt/bin:/usr/local/bin:/usr/bin/:/bin:/usr/local/sbin',
            'LD_LIBRARY_PATH' => '/opt/lib:/opt/lib/bref:/lib64:/usr/lib64:/var/runtime:/var/runtime/lib:/var/task:/var/task/lib',
        ];
    }
}
