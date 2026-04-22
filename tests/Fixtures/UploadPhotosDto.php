<?php

namespace PhalconOpenApi\Tests\Fixtures;

use PhalconOpenApi\Attribute\FileUpload;

class UploadPhotosDto
{
    public string $albumName;

    #[FileUpload(multiple: true)]
    public array $photos;
}
