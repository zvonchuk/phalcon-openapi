<?php

/**
 * Minimal Phalcon stubs for PHPStan analysis.
 * Only covers classes and methods actually used by phalcon-openapi.
 *
 * @phpstan-type ColumnType int
 */

// Phalcon\Db\Column is intentionally omitted — its constants trigger
// PHPStan internal error (ReflectionClassConstant::getType() crash).
// SchemaBuilder.php is excluded from analysis for this reason.

namespace Phalcon\Di {
    interface DiInterface
    {
        public function setShared(string $name, mixed $definition): void;
        public function getShared(string $name): mixed;
        public function has(string $name): bool;
    }
}

namespace Phalcon\Mvc {
    class Controller
    {
        /** @var \Phalcon\Http\Request */
        public \Phalcon\Http\Request $request;
        /** @var \Phalcon\Http\Response */
        public \Phalcon\Http\Response $response;
        /** @var \Phalcon\Di\DiInterface */
        public \Phalcon\Di\DiInterface $di;
        public function onConstruct(): void {}
    }

    interface ModuleDefinitionInterface
    {
        public function registerAutoloaders(?\Phalcon\Di\DiInterface $container = null): void;
        public function registerServices(\Phalcon\Di\DiInterface $container): void;
    }

    class Model
    {
        /** @return static|null */
        public static function findFirst(mixed $parameters = null): ?static { return null; }
        /** @return Model\ResultsetInterface */
        public static function find(mixed $parameters = null): Model\ResultsetInterface {}
        /** @param array<string, mixed> $data */
        public function assign(array $data, mixed $whiteList = null, mixed $dataColumnMap = null): static { return $this; }
        public function save(): bool { return true; }
        public function delete(): bool { return true; }
        /** @param list<string>|null $columns
         *  @return array<string, mixed> */
        public function toArray(?array $columns = null): array { return []; }
        public function initialize(): void {}
        public function setSource(string $source): static { return $this; }
        /** @param array<string, mixed>|null $options */
        public function hasMany(string $fields, string $referenceModel, string $referencedFields, ?array $options = null): Model\RelationInterface {}
        /** @param array<string, mixed>|null $options */
        public function belongsTo(string $fields, string $referenceModel, string $referencedFields, ?array $options = null): Model\RelationInterface {}
    }

    class Router
    {
        public function __construct(bool $defaultRoutes = true) {}
        public function removeExtraSlashes(bool $remove): void {}
        public function setDefaultNamespace(string $namespaceName): void {}
        /** @return list<Router\Route> */
        public function getRoutes(): array { return []; }
        /** @return array<string, string> */
        public function getDefaults(): array { return []; }
        /** @param array<string|int, string>|null $paths */
        public function addGet(string $pattern, mixed $paths = null): Router\Route {}
        /** @param array<string|int, string>|null $paths */
        public function addPost(string $pattern, mixed $paths = null): Router\Route {}
        /** @param array<string|int, string>|null $paths */
        public function addPut(string $pattern, mixed $paths = null): Router\Route {}
        /** @param array<string|int, string>|null $paths */
        public function addDelete(string $pattern, mixed $paths = null): Router\Route {}
    }

    class View
    {
        public function disable(): void {}
    }
}

namespace Phalcon\Mvc\Router {
    class Route
    {
        public function getPattern(): string { return ''; }
        /** @return string|list<string>|null */
        public function getHttpMethods(): string|array|null { return null; }
        /** @return array<string|int, string> */
        public function getPaths(): array { return []; }
    }
}

namespace Phalcon\Mvc\Model {
    interface MetaDataInterface
    {
        /** @return array<string, int> */
        public function getDataTypes(\Phalcon\Mvc\Model $model): array;
        /** @return list<string> */
        public function getNotNullAttributes(\Phalcon\Mvc\Model $model): array;
        /** @return list<string> */
        public function getPrimaryKeyAttributes(\Phalcon\Mvc\Model $model): array;
        /** @return array<string, string>|null */
        public function getColumnMap(\Phalcon\Mvc\Model $model): ?array;
    }

    interface ResultsetInterface
    {
        /** @return list<array<string, mixed>> */
        public function toArray(): array;
    }

    interface RelationInterface {}

    class MetaData
    {
        public function __construct() {}
    }
}

namespace Phalcon\Mvc\Model\MetaData {
    class Memory extends \Phalcon\Mvc\Model\MetaData implements \Phalcon\Mvc\Model\MetaDataInterface
    {
        /** @return array<string, int> */
        public function getDataTypes(\Phalcon\Mvc\Model $model): array { return []; }
        /** @return list<string> */
        public function getNotNullAttributes(\Phalcon\Mvc\Model $model): array { return []; }
        /** @return list<string> */
        public function getPrimaryKeyAttributes(\Phalcon\Mvc\Model $model): array { return []; }
        /** @return array<string, string>|null */
        public function getColumnMap(\Phalcon\Mvc\Model $model): ?array { return null; }
    }
}

namespace Phalcon\Http {
    class Request
    {
        public function getRawBody(): string { return ''; }
        public function getJsonRawBody(bool $associative = false): mixed { return null; }
    }

    class Response implements ResponseInterface
    {
        public function setStatusCode(int $code, ?string $message = null): ResponseInterface { return $this; }
        public function setContentType(string $contentType, ?string $charset = null): ResponseInterface { return $this; }
        public function setJsonContent(mixed $content, int $jsonOptions = 0, int $depth = 512): ResponseInterface { return $this; }
        public function setContent(string $content): ResponseInterface { return $this; }
        public function send(): ResponseInterface { return $this; }
    }

    interface ResponseInterface
    {
        public function setStatusCode(int $code, ?string $message = null): ResponseInterface;
        public function setContentType(string $contentType, ?string $charset = null): ResponseInterface;
        public function setJsonContent(mixed $content, int $jsonOptions = 0, int $depth = 512): ResponseInterface;
        public function setContent(string $content): ResponseInterface;
        public function send(): ResponseInterface;
    }
}

namespace Phalcon\Db\Adapter\Pdo {
    class Mysql
    {
        /** @param array<string, mixed> $descriptor */
        public function __construct(array $descriptor) {}
    }
}

namespace Phalcon\Config {
    /**
     * @implements \ArrayAccess<string, mixed>
     */
    class Config implements \ArrayAccess
    {
        /** @param array<string, mixed> $arrayConfig */
        public function __construct(array $arrayConfig = []) {}
        public function __get(string $name): mixed { return null; }
        /** @return array<string, mixed> */
        public function toArray(): array { return []; }
        public function offsetExists(mixed $offset): bool { return false; }
        public function offsetGet(mixed $offset): mixed { return null; }
        public function offsetSet(mixed $offset, mixed $value): void {}
        public function offsetUnset(mixed $offset): void {}
    }
}
