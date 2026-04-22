<?php

namespace PhalconOpenApi\Tests\Fixtures;

use PhalconOpenApi\Attribute\StringLength;

class OrderDto
{
    #[StringLength(min: 1)]
    public string $orderNumber;

    public AddressDto $shippingAddress;

    /** @var AddressDto[] */
    public array $items = [];
}
