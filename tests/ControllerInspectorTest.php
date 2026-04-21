<?php

namespace PhalconOpenApi\Tests;

use PHPUnit\Framework\TestCase;
use PhalconOpenApi\ControllerInspector;

class ControllerInspectorTest extends TestCase
{
    private ControllerInspector $inspector;

    protected function setUp(): void
    {
        $this->inspector = new ControllerInspector();
    }

    public function testExtractsPathParameter(): void
    {
        $info = $this->inspector->inspect(
            Fixtures\FakeController::class,
            'getAction',
            ['id']
        );

        $this->assertFalse($info['skip']);
        $params = $info['parameters'];
        $this->assertCount(1, $params);
        $this->assertSame('id', $params[0]['name']);
        $this->assertSame('integer', $params[0]['type']);
        $this->assertSame('path', $params[0]['in']);
    }

    public function testExtractsQueryParameters(): void
    {
        $info = $this->inspector->inspect(
            Fixtures\FakeController::class,
            'listAction',
            []
        );

        $params = $info['parameters'];
        $this->assertCount(2, $params);
        $this->assertSame('page', $params[0]['name']);
        $this->assertSame('query', $params[0]['in']);
        $this->assertTrue($params[0]['optional']);
        $this->assertSame(1, $params[0]['default']);
    }

    public function testExtractsBodyClass(): void
    {
        $info = $this->inspector->inspect(
            Fixtures\FakeController::class,
            'createAction',
            []
        );

        $this->assertSame(Fixtures\SimpleDto::class, $info['bodyClass']);
    }

    public function testExtractsReturnClass(): void
    {
        $info = $this->inspector->inspect(
            Fixtures\FakeController::class,
            'getAction',
            ['id']
        );

        $this->assertSame(Fixtures\SimpleDto::class, $info['returnClass']);
    }

    public function testExtractsApiTagFromClass(): void
    {
        $info = $this->inspector->inspect(
            Fixtures\FakeController::class,
            'listAction',
            []
        );

        $this->assertSame(['Fakes'], $info['tags']);
    }

    public function testMethodTagOverridesClassTag(): void
    {
        $info = $this->inspector->inspect(
            Fixtures\FakeController::class,
            'taggedAction',
            []
        );

        $this->assertSame(['Override'], $info['tags']);
    }

    public function testApiIgnoreSetsSkip(): void
    {
        $info = $this->inspector->inspect(
            Fixtures\FakeController::class,
            'hiddenAction',
            []
        );

        $this->assertTrue($info['skip']);
    }

    public function testExtraResponses(): void
    {
        $info = $this->inspector->inspect(
            Fixtures\FakeController::class,
            'getAction',
            ['id']
        );

        $this->assertArrayHasKey(404, $info['extraResponses']);
        $this->assertSame(Fixtures\NotFoundDto::class, $info['extraResponses'][404]);
    }

    public function testSummaryFromDocblock(): void
    {
        $info = $this->inspector->inspect(
            Fixtures\FakeController::class,
            'getAction',
            ['id']
        );

        $this->assertSame('Get a fake by id', $info['summary']);
    }

    public function testConventionTagFromControllerName(): void
    {
        $info = $this->inspector->inspect(
            Fixtures\PlainController::class,
            'listAction',
            []
        );

        $this->assertSame(['Plains'], $info['tags']);
    }

    public function testInferredModelNullWhenNoNamespace(): void
    {
        $info = $this->inspector->inspect(
            Fixtures\FakeController::class,
            'getAction',
            ['id']
        );

        $this->assertNull($info['inferredModel']);
    }

    public function testInferredModelFromNamespace(): void
    {
        $inspector = new ControllerInspector('PhalconOpenApi\\Tests\\Fixtures');
        $info = $inspector->inspect(
            Fixtures\FakeController::class,
            'getAction',
            ['id']
        );

        // FakeController → Fake class — doesn't exist in Fixtures
        $this->assertNull($info['inferredModel']);
    }

    public function testOperationIdFromGetAction(): void
    {
        $info = $this->inspector->inspect(
            Fixtures\FakeController::class,
            'getAction',
            ['id']
        );

        $this->assertSame('getFake', $info['operationId']);
    }

    public function testOperationIdFromListAction(): void
    {
        $info = $this->inspector->inspect(
            Fixtures\FakeController::class,
            'listAction',
            []
        );

        $this->assertSame('listFakes', $info['operationId']);
    }

    public function testOperationIdFromCreateAction(): void
    {
        $info = $this->inspector->inspect(
            Fixtures\FakeController::class,
            'createAction',
            []
        );

        $this->assertSame('createFake', $info['operationId']);
    }

    public function testOperationIdPluralizesCategory(): void
    {
        $info = $this->inspector->inspect(
            Fixtures\PlainController::class,
            'listAction',
            []
        );

        $this->assertSame('listPlains', $info['operationId']);
    }

    public function testDescriptionIsNullByDefault(): void
    {
        $info = $this->inspector->inspect(
            Fixtures\FakeController::class,
            'listAction',
            []
        );

        $this->assertNull($info['description']);
    }

    public function testApiDescriptionAttribute(): void
    {
        $info = $this->inspector->inspect(
            Fixtures\FakeController::class,
            'describedAction',
            []
        );

        $this->assertSame('Short summary', $info['summary']);
        $this->assertSame('Longer description of this endpoint', $info['description']);
    }

    public function testSecurityFromClassAttribute(): void
    {
        $info = $this->inspector->inspect(
            Fixtures\SecureController::class,
            'getAction',
            ['id']
        );

        $this->assertSame([['bearerAuth' => []]], $info['security']);
    }

    public function testSecurityMethodOverridesClass(): void
    {
        $info = $this->inspector->inspect(
            Fixtures\SecureController::class,
            'specialAction',
            []
        );

        $this->assertSame([['apiKey' => []]], $info['security']);
    }

    public function testNoSecurityWhenNotAnnotated(): void
    {
        $info = $this->inspector->inspect(
            Fixtures\FakeController::class,
            'listAction',
            []
        );

        $this->assertNull($info['security']);
    }

    public function testPaginatedAttribute(): void
    {
        $info = $this->inspector->inspect(
            Fixtures\SecureController::class,
            'listAction',
            []
        );

        $this->assertNotNull($info['paginated']);
        $this->assertSame('data', $info['paginated']['dataField']);
    }

    public function testNoPaginatedByDefault(): void
    {
        $info = $this->inspector->inspect(
            Fixtures\FakeController::class,
            'listAction',
            []
        );

        $this->assertNull($info['paginated']);
    }
}
