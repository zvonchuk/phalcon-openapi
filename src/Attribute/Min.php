<?php

namespace PhalconOpenApi\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Min
{
    public function __construct(
        public readonly int|float $value
    ) {}
}
