<?php

namespace PhalconOpenApi\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class ApiPaginated
{
    public function __construct(
        public readonly string $dataField = 'data'
    ) {}
}
