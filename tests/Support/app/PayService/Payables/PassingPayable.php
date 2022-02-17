<?php

namespace Hammerstone\Sidecar\PHP\Tests\Support\App\PayService\Payables;

use Hammerstone\Sidecar\PHP\Tests\Support\App\PayService\Contracts\Payable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;

class PassingPayable implements Payable, ShouldQueue
{
    use Queueable, SerializesModels;

    public function execute()
    {
    }
}
