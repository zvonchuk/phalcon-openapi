<?php

namespace PhalconOpenApi\Tests\Fixtures;

class PlainController
{
    public function listAction(int $page = 1): array
    {
        return [];
    }

    public function getAction(int $id): void {}
}
