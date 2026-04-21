<?php

namespace PhalconOpenApi\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class ApiSecurity
{
    public function __construct(
        public readonly string $name = 'bearerAuth',
        public readonly array $scopes = []
    ) {}
}
