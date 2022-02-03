<?php
/**
 * @author Aaron Francis <aaron@hammerstone.dev|https://twitter.com/aarondfrancis>
 */

namespace Hammerstone\Sidecar\Tests\Unit;

use Hammerstone\Sidecar\Providers\SidecarServiceProvider;
use Orchestra\Testbench\TestCase;

abstract class BaseTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            // @TODO.
        ];
    }
}
