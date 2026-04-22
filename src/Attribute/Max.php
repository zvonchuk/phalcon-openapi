<?php

namespace PhalconOpenApi\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Max
{
    public function __construct(
        public readonly int|float $value
    ) {}
}
