<?php
/**
 * @author Aaron Francis <aarondfrancis@gmail.com|https://twitter.com/aarondfrancis>
 */

namespace Hammerstone\Sidecar\PHP;

use Hammerstone\Sidecar\Region;

class LaravelLambda extends PhpLambda
{
    public function name()
    {
        return 'Laravel-Lambda';
    }

    public function package()
    {
        return Package::make()
            ->withFullApplication()
            ->exclude($this->exclude());
    }

    public function exclude()
    {
        return [
            '.env',
            '.git',
            'node_modules',
            'storage',
            'bootstrap/cache/*',
            'public',
        ];
    }

    public function variables()
    {
        return array_merge(parent::variables(), [
            'APP_CONFIG_CACHE' => '/tmp/storage/bootstrap/cache/config.php',
            'SIDECAR_IS_FULL_LARAVEL' => 'true'
        ]);
    }
}
