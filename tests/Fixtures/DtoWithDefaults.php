<?php

namespace PhalconOpenApi\Tests\Fixtures;

class DtoWithDefaults
{
    public string $title;
    public string $status = 'draft';
    public bool $active = true;
    public float $score;
}
