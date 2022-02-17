<?php

namespace Hammerstone\Sidecar\PHP\Support\Config\Concerns;

trait QueueConfigSetters
{
    public function enableQueueFeature(bool $optin = true, string|array $queues = '*'): self
    {
        config([
            'sidecar.queue.enabled' => true,
            'sidecar.queue.allowed_queues' => $queues,
            'sidecar.queue.opt_in_required' => $optin,
        ]);

        return $this;
    }

    public function disableQueueFeature(): self
    {
        config(['sidecar.queue.enabled' => false]);

        return $this;
    }
}
