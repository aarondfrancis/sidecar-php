<?php

namespace Hammerstone\Sidecar\PHP\Tests\Support;

use Closure;
use Exception;
use Hammerstone\Sidecar\Events\AfterFunctionExecuted;
use Hammerstone\Sidecar\Events\BeforeFunctionExecuted;
use Hammerstone\Sidecar\PHP\LaravelLambda;
use Hammerstone\Sidecar\PHP\PhpLambda;
use Hammerstone\Sidecar\PHP\Support\Config\SidecarConfig;
use Hammerstone\Sidecar\PHP\Support\Decorator;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\DatabaseQueue;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Facades\Queue;

class QueueTestHelper extends Decorator
{
    public $job;
    private Closure $dispatcher;
    private QueueContract $queue;
    private ?Job $failedJob = null;
    private ?Job $releasedJob = null;
    private static array $queueNames = [];

    public function __construct($job, ?Closure $dispatcher = null)
    {
        SidecarConfig::make()->queueDriverSupported();

        $this->job = $job;
        $this->dispatcher = $dispatcher ?? fn ($job) => dispatch($job);

        app('events')->listen(JobFailed::class, function (JobFailed $event) {
            $this->failedJob = $event->job;
        });

        $this->mockWhenConfigured();

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

    public function mockWhenConfigured(): self
    {
        return config('sidecar.testing.mock_php_lambda')
            ? $this->mock()
            : $this;
    }

    public function mock(): self
    {
        $defaultQueue = config('queue.default');
        app('events')->listen(BeforeFunctionExecuted::class, fn (BeforeFunctionExecuted $event) => config(['queue.default' => 'null', 'queue.connections.null.driver' => 'null']));
        app('events')->listen(AfterFunctionExecuted::class, fn (AfterFunctionExecuted $event) => config(['queue.default' => $defaultQueue]));
        PhpLambda::mock();

        return $this;
    }

    public function queue(): QueueContract
    {
        return app(QueueContract::class);
    }

    public function with(array $payload)
    {
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

        $queue = static::$queueNames[] = $this->getQueueName();

        return $dispatcher((clone $this->job)->onQueue($queue));
    }

    public function runQueueWorker(): self
    {
        test()->artisan('queue:work', [
            '--once' => true,
            '--stop-when-empty' => true,
            '--queue' => $this->getQueueName(),
            $this->queue()->getConnectionName(),
        ])->execute();

        return $this;
    }

    public function getQueueName(): string
    {
        return $this->job->queue ?? 'default';
    }

    public function clearQueue(): self
    {
        $this->queue()->clear($this->getQueueName());

        return $this;
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
        expect($this->countQueuedJobs() === 0)->toBe(true);

        return $this;
    }

    public function assertNotDeleted(): self
    {
        expect($this->countQueuedJobs() === 0)->toBe(false);

        return $this;
    }

    public function assertReleased(): self
    {
        expect($this->releasedJobRecord() === null)->toBe(false);

        return $this;
    }

    public function assertNotReleased(): self
    {
        expect($this->releasedJobRecord() === null)->toBe(true);

        return $this;
    }

    public function assertDelayed(int $seconds): self
    {
        expect(optional($this->releasedJobRecord())->available_at - optional($this->releasedJobRecord())->created_at)->toBe($seconds);

        return $this;
    }

    public function assertNotDelayed(): self
    {
        return $this->assertDelayed(0);
    }

    public function assertTries(int $tries): self
    {
        expect(optional($this->failedJob())->attempts() ?? optional($this->releasedJobRecord())->attempts + 1)->toBe($tries);

        return $this;
    }

    public function assertMaxTries(int $limit): self
    {
        expect(optional($this->failedJob())->maxTries() ?? json_decode(optional($this->releasedJobRecord())->payload ?? '{}')->maxTries ?? 1)->toBe($limit);

        return $this;
    }

    public function assertTriesRemaining(int $remainingAttempts): self
    {
        $tries = optional($this->failedJob())->attempts() ?? optional($this->releasedJobRecord())->attempts;
        $maxTries = optional($this->failedJob())->maxTries() ?? json_decode(optional($this->releasedJobRecord())->payload ?? '{}')->maxTries ?? 1;
        $remaining = $maxTries - $tries - 1;

        expect($remaining < 0 ? 0 : $remaining)->toBe($remainingAttempts);

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
        return $this->queue()->size($this->getQueueName());
    }

    private function failedJob(): ?Job
    {
        return $this->failedJob;
    }

    private function releasedJobRecord()
    {
        throw_unless($this->queue() instanceof DatabaseQueue, new Exception(
            'Can only assert released job on a database queue for now.'
        ));

        return $this->queue()
            ->getDatabase()
            ->table(config('queue.connections.database.table', 'jobs'))
            ->first();
    }
}
