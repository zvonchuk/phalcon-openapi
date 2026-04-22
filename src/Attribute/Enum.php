<?php

namespace PhalconOpenApi\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Enum
{
    public readonly array $values;

    public function __construct(array $values)
    {
        $this->values = $values;
    }
}
