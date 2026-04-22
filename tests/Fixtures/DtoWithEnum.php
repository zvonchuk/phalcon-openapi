<?php

namespace PhalconOpenApi\Tests\Fixtures;

use PhalconOpenApi\Attribute\Enum;
use PhalconOpenApi\Attribute\NotBlank;
use PhalconOpenApi\Attribute\Url;

class DtoWithEnum
{
    #[NotBlank]
    public string $name;

    #[Enum(['active', 'inactive', 'banned'])]
    public string $status;

    #[Url]
    public ?string $website = null;
}
