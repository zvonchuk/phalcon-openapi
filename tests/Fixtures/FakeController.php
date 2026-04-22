<?php

namespace PhalconOpenApi\Tests\Fixtures;

use PhalconOpenApi\Attribute\ApiDescription;
use PhalconOpenApi\Attribute\ApiTag;
use PhalconOpenApi\Attribute\ApiIgnore;
use PhalconOpenApi\Attribute\ApiResponse;

#[ApiTag('Fakes')]
class FakeController
{
    /** Get a fake by id */
    #[ApiResponse(404, NotFoundDto::class)]
    public function getAction(int $id): SimpleDto
    {
        return new SimpleDto();
    }

    public function listAction(int $page = 1, int $limit = 20): array
    {
        return [];
    }

    public function createAction(SimpleDto $body): SimpleDto
    {
        return new SimpleDto();
    }

    #[ApiIgnore]
    public function hiddenAction(): void {}

    #[ApiTag('Override')]
    public function taggedAction(): void {}

    #[ApiDescription(summary: 'Short summary', description: 'Longer description of this endpoint')]
    public function describedAction(): void {}

    public function deleteAction(int $id): void {}

    public function uploadAction(UploadAvatarDto $body): SimpleDto
    {
        return new SimpleDto();
    }
}
