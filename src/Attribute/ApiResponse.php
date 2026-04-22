<?php

namespace PhalconOpenApi\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class ApiResponse
{
    public function __construct(
        public readonly int $statusCode,
        public readonly string $dtoClass
    ) {}
}
