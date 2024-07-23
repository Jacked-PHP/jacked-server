<?php

namespace JackedPhp\JackedServer\Data\Traits;

trait DataBagHelper
{
    public static function from(mixed $values): static
    {
        /** @var static $data */
        $data = parent::from($values);

        return $data;
    }

    public function with(mixed ...$values): static
    {
        /** @var static $data */
        $data = parent::with(...$values);

        return $data;
    }
}
