<?php

namespace Hammerstone\Sidecar\PHP\Queue;

use Illuminate\Queue\Jobs\SyncJob as BaseSyncJob;

class SyncJob extends BaseSyncJob
{
    private $delay = 0;

    public function release($delay = 0)
    {
        parent::release($this->delay = $delay);
    }

    public function getReleaseDelay()
    {
        return $this->delay;
    }
}
