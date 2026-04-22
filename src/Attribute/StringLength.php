<?php

namespace PhalconOpenApi\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class StringLength
{
    public function __construct(
        public readonly ?int $min = null,
        public readonly ?int $max = null
    ) {}
}
