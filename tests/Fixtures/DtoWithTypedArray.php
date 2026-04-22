<?php

namespace PhalconOpenApi\Tests\Fixtures;

class DtoWithTypedArray
{
    public string $name;

    /** @var AddressDto[] */
    public array $addresses = [];
}
