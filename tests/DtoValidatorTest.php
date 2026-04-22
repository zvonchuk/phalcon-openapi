<?php

namespace PhalconOpenApi\Tests;

use PHPUnit\Framework\TestCase;
use PhalconOpenApi\DtoValidator;

class DtoValidatorTest extends TestCase
{
    private DtoValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new DtoValidator();
    }

    // --- Basic validation ---

    public function testRequiredFieldMissing(): void
    {
        $errors = $this->validator->validate(Fixtures\SimpleDto::class, []);
        $this->assertContains('name is required', $errors);
        $this->assertContains('age is required', $errors);
    }

    public function testValidData(): void
    {
        $errors = $this->validator->validate(Fixtures\SimpleDto::class, [
            'name' => 'John',
            'age'  => 25,
        ]);
        $this->assertEmpty($errors);
    }

    public function testNullableFieldOptional(): void
    {
        $errors = $this->validator->validate(Fixtures\SimpleDto::class, [
            'name' => 'John',
            'age'  => 25,
            // phone is ?string, so omitting is fine
        ]);
        $this->assertEmpty($errors);
    }

    public function testTypeCheckFails(): void
    {
        $errors = $this->validator->validate(Fixtures\SimpleDto::class, [
            'name' => 'John',
            'age'  => 'not a number',
        ]);
        $this->assertContains('age must be of type int', $errors);
    }

    // --- Enum ---

    public function testEnumValidValue(): void
    {
        $errors = $this->validator->validate(Fixtures\DtoWithEnum::class, [
            'name'   => 'John',
            'status' => 'active',
        ]);
        $this->assertEmpty($errors);
    }

    public function testEnumInvalidValue(): void
    {
        $errors = $this->validator->validate(Fixtures\DtoWithEnum::class, [
            'name'   => 'John',
            'status' => 'deleted',
        ]);
        $this->assertContains('status must be one of: active, inactive, banned', $errors);
    }

    // --- Url ---

    public function testUrlValid(): void
    {
        $errors = $this->validator->validate(Fixtures\DtoWithEnum::class, [
            'name'    => 'John',
            'status'  => 'active',
            'website' => 'https://example.com',
        ]);
        $this->assertEmpty($errors);
    }

    public function testUrlInvalid(): void
    {
        $errors = $this->validator->validate(Fixtures\DtoWithEnum::class, [
            'name'    => 'John',
            'status'  => 'active',
            'website' => 'not-a-url',
        ]);
        $this->assertContains('website must be a valid URL', $errors);
    }

    // --- NotBlank ---

    public function testNotBlankRejectsEmpty(): void
    {
        $errors = $this->validator->validate(Fixtures\DtoWithEnum::class, [
            'name'   => '   ',
            'status' => 'active',
        ]);
        $this->assertContains('name must not be blank', $errors);
    }

    // --- Nested DTO validation ---

    public function testNestedDtoValid(): void
    {
        $errors = $this->validator->validate(Fixtures\OrderDto::class, [
            'orderNumber'     => 'ORD-001',
            'shippingAddress' => [
                'street' => '123 Main St',
                'city'   => 'NYC',
                'zip'    => '10001',
            ],
        ]);
        $this->assertEmpty($errors);
    }

    public function testNestedDtoValidationErrors(): void
    {
        $errors = $this->validator->validate(Fixtures\OrderDto::class, [
            'orderNumber'     => 'ORD-001',
            'shippingAddress' => [
                'street' => '123 Main St',
                // missing city and zip
            ],
        ]);
        $this->assertContains('shippingAddress.city is required', $errors);
        $this->assertContains('shippingAddress.zip is required', $errors);
    }

    public function testNestedDtoRejectsNonArray(): void
    {
        $errors = $this->validator->validate(Fixtures\OrderDto::class, [
            'orderNumber'     => 'ORD-001',
            'shippingAddress' => 'not an object',
        ]);
        $this->assertNotEmpty($errors);
    }

    // --- Typed array validation ---

    public function testTypedArrayValid(): void
    {
        $errors = $this->validator->validate(Fixtures\OrderDto::class, [
            'orderNumber'     => 'ORD-001',
            'shippingAddress' => ['street' => 'A', 'city' => 'B', 'zip' => 'C'],
            'items' => [
                ['street' => '1', 'city' => 'X', 'zip' => '000'],
                ['street' => '2', 'city' => 'Y', 'zip' => '111'],
            ],
        ]);
        $this->assertEmpty($errors);
    }

    public function testTypedArrayValidationErrors(): void
    {
        $errors = $this->validator->validate(Fixtures\OrderDto::class, [
            'orderNumber'     => 'ORD-001',
            'shippingAddress' => ['street' => 'A', 'city' => 'B', 'zip' => 'C'],
            'items' => [
                ['street' => '1'],  // missing city and zip
            ],
        ]);
        $this->assertContains('items[0].city is required', $errors);
        $this->assertContains('items[0].zip is required', $errors);
    }

    // --- Hydration ---

    public function testHydrateSimple(): void
    {
        $dto = $this->validator->hydrate(Fixtures\SimpleDto::class, [
            'name' => 'John',
            'age'  => 25,
        ]);
        $this->assertSame('John', $dto->name);
        $this->assertSame(25, $dto->age);
    }

    public function testHydrateNestedDto(): void
    {
        $dto = $this->validator->hydrate(Fixtures\OrderDto::class, [
            'orderNumber'     => 'ORD-001',
            'shippingAddress' => [
                'street' => '123 Main St',
                'city'   => 'NYC',
                'zip'    => '10001',
            ],
        ]);

        $this->assertSame('ORD-001', $dto->orderNumber);
        $this->assertInstanceOf(Fixtures\AddressDto::class, $dto->shippingAddress);
        $this->assertSame('NYC', $dto->shippingAddress->city);
    }

    public function testHydrateTypedArray(): void
    {
        $dto = $this->validator->hydrate(Fixtures\OrderDto::class, [
            'orderNumber'     => 'ORD-001',
            'shippingAddress' => ['street' => 'A', 'city' => 'B', 'zip' => 'C'],
            'items' => [
                ['street' => '1', 'city' => 'X', 'zip' => '000'],
            ],
        ]);

        $this->assertCount(1, $dto->items);
        $this->assertInstanceOf(Fixtures\AddressDto::class, $dto->items[0]);
        $this->assertSame('X', $dto->items[0]->city);
    }
}
