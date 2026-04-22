<?php

namespace PhalconOpenApi\Tests\Fixtures;

use PhalconOpenApi\Attribute\FileUpload;
use PhalconOpenApi\Attribute\StringLength;

class UploadAvatarDto
{
    #[StringLength(min: 1, max: 255)]
    public string $name;

    #[FileUpload]
    public string $avatar;
}
