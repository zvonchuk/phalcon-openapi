<?php

namespace PhalconOpenApi\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Pattern
{
    public function __construct(
        public readonly string $regex
    ) {}
}
