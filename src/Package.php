<?php
/**
 * @author Aaron Francis <aarondfrancis@gmail.com|https://twitter.com/aarondfrancis>
 */

namespace Hammerstone\Sidecar\PHP;

use Hammerstone\Sidecar\Package as Base;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class Package extends Base
{
    public function includeVendor($path)
    {
        $paths = array_map(fn ($path) => $path === '*' ? '' : $path, Arr::wrap($path));

        $includes = [];

        foreach ($paths as $path) {
            $includes[$this->vendorPath($path)] = rtrim("vendor/{$path}", '/');
        }

        return $this->includeExactly($includes);
    }

    public function withRuntimeSupport()
    {
        return $this->includeExactly([
            __DIR__ . '/Runtime' => '',
        ]);
    }

    public function withVanillaPhp()
    {
        return $this->withRuntimeSupport()
            ->includeStrings([
                // Our modified autoloader that doesn't care if files don't exist.
                'vendor/composer/autoload_real.php' => $this->modifiedAutoloader(),
            ])
            ->includeVendor([
                // Composer's autoloader entry point.
                'autoload.php',

                // Ship everything Composer needs, so we don't have
                // to recreate auto-loading.
                'composer',

                // Required for deserializing the closure on Lambda.
                'laravel/serializable-closure',

                // The support files from this very package.
                'hammerstone/sidecar-php/src/Support',

                // Required to support the Runtime
                'laravel/vapor-core/src/Runtime/NotifiesLambda.php',
                'laravel/vapor-core/src/Runtime/LambdaContainer.php',
                'laravel/vapor-core/src/Runtime/LambdaRuntime.php',
                'laravel/vapor-core/src/Runtime/LambdaInvocation.php',
                'laravel/framework/src/Illuminate/Support/Str.php',
                'laravel/framework/src/Illuminate/Macroable/Traits/Macroable.php',
                'symfony/process',
                'symfony/polyfill-php80',
            ]);
    }

    public function withFullApplication()
    {
        return $this->withRuntimeSupport()
            // We're going to include the entire Laravel application, so
            // set the base path to the base path of the Laravel app.
            ->setBasePath(base_path())
            // And then include everything.
            ->include('*')
            ->includeVendor('*');
    }

    protected function vendorPath($path)
    {
        $path = Str::of($path)->ltrim('/')->prepend('vendor/');

        return app()->runningUnitTests() ? realpath(__DIR__ . "/../{$path}") : base_path($path);
    }

    protected function modifiedAutoloader()
    {
        // Composer will autoload a set of files if requested by certain composer
        // packages. Because we aren't shipping all the packages, composer may
        // try to load files that don't exist. In reality, this shouldn't be
        // a problem because those files will never get used, so we're
        // just going to eat the error and let the developer know.
        $replacement = <<<'EOT'
if (! file_exists($file)) {
    fwrite(STDERR, "Composer file not found. This may or may not be an issue! Path: $file." . PHP_EOL);
    return;
}

require $file;
EOT;

        return str_replace(
            'require $file;', $replacement, file_get_contents($this->vendorPath('composer/autoload_real.php'))
        );
    }
}
