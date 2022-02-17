<?php

namespace Hammerstone\Sidecar\PHP\Tests\Support;

use Closure;
use Hammerstone\Sidecar\PHP\Support\Config\SidecarConfig;

class SidecarTestHelper extends SidecarConfig
{
    private static bool $recording = false;
    private static array $executions = [];
    private static array $transformers = [];
    private static array $settledHooks = [];

    public function __construct()
    {
        if (static::$recording) {
            return;
        }

        static::$recording = true;
        // Will register listeners to allow for assertions if not done already.
        // First run transformers, then run onSettled.
        // Need to track executed lambdas.
    }

    public static function record(): self
    {
        return new static;
    }

    public function fresh(): self
    {
        static::reset();

        return $this;
    }

    public static function reset(): void
    {
        static::$recording = false;
        static::$transformers = [];
        static::$settledHooks = [];
    }

    public function mock(array $result): self
    {
        // it should mock \Illuminate\Support\Facades\Http so \Hammerstone\Sidecar\LambdaFunction is essentially a mock.

        return $this;
    }

    public function transform(Closure $callback): self
    {
        static::$transformers[] = $callback;

        return $this;
    }

    public function onSettled(Closure $callback): self
    {
        static::$settledHooks[] = $callback;

        return $this;
    }

    public function assertWasExecuted(int $times, string $lambda, ?Closure $filter = null): self
    {
        $executions = collect(static::$executions[$lambda] ?? [])->when(
            $filter !== null,
            fn ($executions) => $executions->filter($filter),
        );

        expect($executions->count())->toBe($times);

        return $this;
    }
}
