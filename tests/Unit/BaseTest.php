<?php
/**
 * @author Aaron Francis <aaron@hammerstone.dev|https://twitter.com/aarondfrancis>
 */

namespace Hammerstone\Sidecar\PHP\Tests\Unit;

use Hammerstone\Sidecar\PHP\Providers\SidecarPhpServiceProvider;
use Hammerstone\Sidecar\Providers\SidecarServiceProvider;
use Orchestra\Testbench\TestCase;

abstract class BaseTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            SidecarPhpServiceProvider::class,
        ];
    }
}
