<?php

namespace Hammerstone\Sidecar\PHP\Tests\Support;

use Closure;
use Hammerstone\Sidecar\PHP\LaravelLambda;
use Hammerstone\Sidecar\PHP\Support\Config\SidecarConfig;
use Hammerstone\Sidecar\PHP\Support\Decorator;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

class QueueTestHelper extends Decorator
{
    public string $id; // debugging
    public $job; // or $queueable?
    private Closure $dispatcher;
    private QueueContract $queue;
    private static array $queueNames = [];

    public function __construct($job, ?Closure $dispatcher = null)
    {
        SidecarConfig::make()->queueDriverSupported();

        $this->id = Str::uuid(); // debugging
        $this->job = $job;
        $this->dispatcher = $dispatcher ?? fn ($job) => dispatch($job);

        parent::__construct($this->job);
    }

    public static function reset(): void
    {
        static::$queueNames = collect(static::$queueNames)
            ->unique()
            ->values()
            ->each(fn (string $queueName) => Queue::clear($queueName))
            ->all();
    }

    public function queue(): QueueContract
    {
        return app(QueueContract::class);
    }

    public function with(array $payload)
    {
        // add callback to merge into payload.
        $this->queue()->createPayloadUsing(fn () => $payload);

        return $this;
    }

    public function payload(): array
    {
        $command = $this->job;

        return Closure::bind(function () use ($command) {
            return $this->createPayloadArray($command, $command->queue);
        }, $this->queue(), $this->queue()::class)();
    }

    public function dispatch()
    {
        $dispatcher = $this->dispatcher;

        $queue = static::$queueNames[] = $this->getNamespacedQueueName();

        return $dispatcher((clone $this->job)->onQueue($queue));
    }

    public function runQueueWorker(): self
    {
        test()->artisan('queue:work', [
            '--once' => true,
            '--stop-when-empty' => true,
            '--queue' => $this->getNamespacedQueueName(),
            $this->queue()->getConnectionName(),
        ]);

        return $this;
    }

    public function getQueueName(): string
    {
        return $this->job->queue ?? 'default';
    }

    public function getNamespacedQueueName(): string
    {
        // debugging
        return false
            ? sprintf('%s:%s(%s)', $this->getQueueName(), (new \ReflectionClass($this->job))->getShortName(), $this->id)
            : $this->getQueueName();
    }

    public function assertFailed(int $times = 1): self
    {
        $failedJobs = collect(app(FailedJobProviderInterface::class)->all());

        expect($failedJobs->count())->toBe($times);

        return $this;
    }

    public function assertNotFailed(): self
    {
        return $this->assertFailed(0);
    }

    public function assertQueued(int $times = 1): self
    {
        expect($this->countQueuedJobs())->toBe($times);

        return $this;
    }

    public function assertNotQueued(): self
    {
        return $this->assertQueued(0);
    }

    public function assertDeleted(): self
    {
        expect($this->countQueuedJobs() === 0 || $this->releasedJob() !== null)->toBe(true);

        return $this;
    }

    public function assertNotDeleted(): self
    {
        expect($this->countQueuedJobs() === 0 || $this->releasedJob() !== null)->toBe(false);

        return $this;
    }

    public function assertReleased(): self
    {
        expect($this->releasedJob() === null)->toBe(false);

        return $this;
    }

    public function assertNotReleased(): self
    {
        expect($this->releasedJob() === null)->toBe(true);

        return $this;
    }

    public function assertExecutedOnLambda(int $times = 1, ?Closure $filter = null): self
    {
        SidecarTestHelper::record()->assertWasExecuted($times, LaravelLambda::class, $filter);

        return $this;
    }

    public function assertNotExecutedOnLambda(): self
    {
        return $this->assertExecutedOnLambda(0);
    }

    private function countQueuedJobs(): int
    {
        return $this->queue()->size($this->getNamespacedQueueName());
    }

    private function releasedJob(): ?self
    {
        // TODO: pop+push; return new instance if tries are different else null.

        return null;
    }
}
