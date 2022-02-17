<?php

namespace Hammerstone\Sidecar\PHP\Support\Config;

class SidecarConfig
{
    use Concerns\QueueConfigGetters;
    use Concerns\QueueConfigSetters;

    public static function make()
    {
        return new static;
    }
}
