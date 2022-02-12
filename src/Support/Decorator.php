<?php

namespace Hammerstone\Sidecar\PHP\Support;

use Illuminate\Support\Traits\ForwardsCalls;

abstract class Decorator
{
    use ForwardsCalls;

    private object $decorated;

    public function __construct(object $decorated)
    {
        $this->decorated = $decorated;
    }

    public function getDecorated(): object
    {
        return $this->decorated;
    }

    public function __get($attribute)
    {
        return $this->decorated->{$attribute};
    }

    public function __set($attribute, $value)
    {
        $this->decorated->{$attribute} = $value;
    }

    public function __call($method, $parameters)
    {
        return $this->forwardDecoratedCallTo($this->decorated, $method, $parameters);
    }
}
