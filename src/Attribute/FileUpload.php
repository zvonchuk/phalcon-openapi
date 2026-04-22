<?php

namespace PhalconOpenApi\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class FileUpload
{
    public function __construct(
        public readonly bool $multiple = false
    ) {}
}
