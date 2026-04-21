<?php

namespace PhalconOpenApi\Tests;

use PHPUnit\Framework\TestCase;
use PhalconOpenApi\SchemaBuilder;

class SchemaBuilderTest extends TestCase
{
    public function testBuildsDtoSchemaWithScalarTypes(): void
    {
        $builder = new SchemaBuilder();
        $schema = $builder->build(Fixtures\SimpleDto::class);

        $this->assertSame('object', $schema['type']);
        $this->assertSame('string', $schema['properties']['name']['type']);
        $this->assertSame('integer', $schema['properties']['age']['type']);
        $this->assertContains('name', $schema['required']);
        $this->assertContains('age', $schema['required']);
    }

    public function testNullablePropertyUsesTypeArray(): void
    {
        $builder = new SchemaBuilder();
        $schema = $builder->build(Fixtures\SimpleDto::class);

        // OpenAPI 3.1: nullable expressed as type array
        $this->assertSame(['string', 'null'], $schema['properties']['phone']['type']);
        $this->assertNotContains('phone', $schema['required']);
    }

    public function testPropertyWithDefaultNotRequired(): void
    {
        $builder = new SchemaBuilder();
        $schema = $builder->build(Fixtures\DtoWithDefaults::class);

        $this->assertNotContains('status', $schema['required']);
    }

    public function testBooleanType(): void
    {
        $builder = new SchemaBuilder();
        $schema = $builder->build(Fixtures\DtoWithDefaults::class);

        $this->assertSame('boolean', $schema['properties']['active']['type']);
    }

    public function testFloatType(): void
    {
        $builder = new SchemaBuilder();
        $schema = $builder->build(Fixtures\DtoWithDefaults::class);

        $this->assertSame('number', $schema['properties']['score']['type']);
    }

    public function testSchemasCached(): void
    {
        $builder = new SchemaBuilder();
        $schema1 = $builder->build(Fixtures\SimpleDto::class);
        $schema2 = $builder->build(Fixtures\SimpleDto::class);

        $this->assertSame($schema1, $schema2);
    }

    public function testGetAllSchemasReturnsCollected(): void
    {
        $builder = new SchemaBuilder();
        $builder->build(Fixtures\SimpleDto::class);
        $builder->build(Fixtures\DtoWithDefaults::class);

        $all = $builder->getAllSchemas();
        $this->assertArrayHasKey('SimpleDto', $all);
        $this->assertArrayHasKey('DtoWithDefaults', $all);
    }

    public function testArrayPropertyType(): void
    {
        $builder = new SchemaBuilder();
        $schema = $builder->build(Fixtures\DtoWithArray::class);

        $this->assertSame('array', $schema['properties']['tags']['type']);
    }

    public function testNestedDtoBecomesRef(): void
    {
        $builder = new SchemaBuilder();
        $schema = $builder->build(Fixtures\DtoWithNestedObject::class);

        $this->assertSame(
            '#/components/schemas/AddressDto',
            $schema['properties']['address']['$ref']
        );

        // The nested DTO should also be built
        $all = $builder->getAllSchemas();
        $this->assertArrayHasKey('AddressDto', $all);
        $this->assertSame('string', $all['AddressDto']['properties']['street']['type']);
    }

    public function testTypedArrayBecomesRefItems(): void
    {
        $builder = new SchemaBuilder();
        $schema = $builder->build(Fixtures\DtoWithTypedArray::class);

        $this->assertSame('array', $schema['properties']['addresses']['type']);
        $this->assertSame(
            '#/components/schemas/AddressDto',
            $schema['properties']['addresses']['items']['$ref']
        );
    }
}
