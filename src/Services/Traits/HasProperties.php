<?php

namespace JackedPhp\JackedServer\Services\Traits;

use BadMethodCallException;

trait HasProperties
{
    public function __call(string $name, array $arguments): static
    {
        if (!property_exists($this, $name)) {
            throw new BadMethodCallException("Undefined method called: {$name}");
        }

        $this->$name = $arguments[0];

        return $this;
    }
}
