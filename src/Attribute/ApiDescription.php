<?php

namespace PhalconOpenApi\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class ApiDescription
{
    public function __construct(
        public readonly string $summary = '',
        public readonly string $description = ''
    ) {}
}
