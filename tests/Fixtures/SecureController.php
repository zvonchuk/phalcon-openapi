<?php

namespace PhalconOpenApi\Tests\Fixtures;

use PhalconOpenApi\Attribute\ApiPaginated;
use PhalconOpenApi\Attribute\ApiSecurity;

#[ApiSecurity('bearerAuth')]
class SecureController
{
    #[ApiPaginated]
    public function listAction(int $page = 1): array
    {
        return [];
    }

    public function getAction(int $id): SimpleDto
    {
        return new SimpleDto();
    }

    #[ApiSecurity('apiKey')]
    public function specialAction(): void {}
}
