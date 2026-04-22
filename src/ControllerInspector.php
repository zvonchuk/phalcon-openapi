<?php

namespace PhalconOpenApi;

use PhalconOpenApi\Attribute\ApiDescription;
use PhalconOpenApi\Attribute\ApiIgnore;
use PhalconOpenApi\Attribute\ApiPaginated;
use PhalconOpenApi\Attribute\ApiResponse;
use PhalconOpenApi\Attribute\ApiSecurity;
use PhalconOpenApi\Attribute\ApiTag;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

class ControllerInspector
{
    public function __construct(
        private string $modelNamespace = ''
    ) {}

    /**
     * @param string[] $pathParams
     */
    public function inspect(string $controllerClass, string $actionMethod, array $pathParams): array
    {
        $refClass = new ReflectionClass($controllerClass);
        $refMethod = $refClass->getMethod($actionMethod);

        // Check ApiIgnore
        if (!empty($refMethod->getAttributes(ApiIgnore::class))) {
            return ['skip' => true];
        }

        // Tags: attribute > convention (controller name without suffix)
        $tags = $this->extractTags($refClass, $refMethod);

        // Summary and description from ApiDescription attribute or docblock
        [$summary, $description] = $this->extractDescription($refMethod);

        // operationId
        $operationId = $this->buildOperationId($refClass, $refMethod);

        // Parameters and body
        $parameters = [];
        $bodyClass = null;

        foreach ($refMethod->getParameters() as $param) {
            $type = $param->getType();
            if (!$type instanceof ReflectionNamedType) {
                continue;
            }

            $typeName = $type->getName();

            if ($this->isScalar($typeName)) {
                $in = in_array($param->getName(), $pathParams, true) ? 'path' : 'query';
                $paramInfo = [
                    'name'     => $param->getName(),
                    'type'     => $this->mapPhpType($typeName),
                    'in'       => $in,
                    'optional' => $param->isOptional(),
                ];
                if ($param->isOptional() && $param->isDefaultValueAvailable()) {
                    $paramInfo['default'] = $param->getDefaultValue();
                }
                if ($type->allowsNull()) {
                    $paramInfo['nullable'] = true;
                }
                $parameters[] = $paramInfo;
            } elseif (!str_starts_with($typeName, 'Phalcon\\')) {
                $bodyClass = $typeName;
            }
        }

        // Return type (from PHP type hint)
        $returnClass = null;
        $returnType = $refMethod->getReturnType();
        if ($returnType instanceof ReflectionNamedType) {
            $rtName = $returnType->getName();
            if (!$this->isScalar($rtName) && $rtName !== 'void' && $rtName !== 'array') {
                $returnClass = $rtName;
            }
        }

        // Extra responses from ApiResponse attributes
        $extraResponses = [];
        foreach ($refMethod->getAttributes(ApiResponse::class) as $attr) {
            $instance = $attr->newInstance();
            $extraResponses[$instance->statusCode] = $instance->dtoClass;
        }

        // Infer model from controller name convention
        $inferredModel = $this->inferModel($refClass);

        // Security: method-level > class-level
        $security = $this->extractSecurity($refClass, $refMethod);

        // Pagination
        $paginated = $this->extractPaginated($refMethod);

        return [
            'tags'           => $tags,
            'summary'        => $summary,
            'description'    => $description,
            'operationId'    => $operationId,
            'parameters'     => $parameters,
            'bodyClass'      => $bodyClass,
            'returnClass'    => $returnClass,
            'extraResponses' => $extraResponses,
            'inferredModel'  => $inferredModel,
            'security'       => $security,
            'paginated'      => $paginated,
            'skip'           => false,
        ];
    }

    private function extractSecurity(ReflectionClass $class, ReflectionMethod $method): ?array
    {
        // Method-level overrides class-level
        $methodAttrs = $method->getAttributes(ApiSecurity::class);
        if (!empty($methodAttrs)) {
            $instance = $methodAttrs[0]->newInstance();
            return [[$instance->name => $instance->scopes]];
        }

        $classAttrs = $class->getAttributes(ApiSecurity::class);
        if (!empty($classAttrs)) {
            $instance = $classAttrs[0]->newInstance();
            return [[$instance->name => $instance->scopes]];
        }

        return null;
    }

    private function extractPaginated(ReflectionMethod $method): ?array
    {
        $attrs = $method->getAttributes(ApiPaginated::class);
        if (!empty($attrs)) {
            $instance = $attrs[0]->newInstance();
            return ['dataField' => $instance->dataField];
        }

        return null;
    }

    private function extractTags(ReflectionClass $class, ReflectionMethod $method): array
    {
        // Method-level tag overrides class-level
        $methodAttrs = $method->getAttributes(ApiTag::class);
        if (!empty($methodAttrs)) {
            return [$methodAttrs[0]->newInstance()->name];
        }

        $classAttrs = $class->getAttributes(ApiTag::class);
        if (!empty($classAttrs)) {
            return [$classAttrs[0]->newInstance()->name];
        }

        // Convention: UserController → "Users", CategoryController → "Categories"
        $shortName = $class->getShortName();
        if (str_ends_with($shortName, 'Controller')) {
            $name = substr($shortName, 0, -10);
            return [$this->pluralize($name)];
        }

        return [];
    }

    private function extractDescription(ReflectionMethod $method): array
    {
        // ApiDescription attribute takes precedence
        $attrs = $method->getAttributes(ApiDescription::class);
        if (!empty($attrs)) {
            $instance = $attrs[0]->newInstance();
            $summary = $instance->summary !== '' ? $instance->summary : null;
            $description = $instance->description !== '' ? $instance->description : null;
            return [$summary, $description];
        }

        // Fall back to docblock
        $summary = $this->extractSummaryFromDocblock($method);
        return [$summary, null];
    }

    private function buildOperationId(ReflectionClass $class, ReflectionMethod $method): string
    {
        $shortName = $class->getShortName();
        $entity = str_ends_with($shortName, 'Controller')
            ? substr($shortName, 0, -10)
            : $shortName;

        $action = $method->getName();
        $action = str_ends_with($action, 'Action')
            ? substr($action, 0, -6)
            : $action;

        if ($action === 'list') {
            return $action . $this->pluralize($entity);
        }

        return $action . $entity;
    }

    private function pluralize(string $name): string
    {
        if (str_ends_with($name, 'y') && !str_ends_with($name, 'ey')) {
            return substr($name, 0, -1) . 'ies';
        }
        if (str_ends_with($name, 's') || str_ends_with($name, 'sh') || str_ends_with($name, 'ch')) {
            return $name . 'es';
        }
        return $name . 's';
    }

    private function inferModel(ReflectionClass $class): ?string
    {
        if ($this->modelNamespace === '') {
            return null;
        }

        $shortName = $class->getShortName();
        if (!str_ends_with($shortName, 'Controller')) {
            return null;
        }

        $modelName = substr($shortName, 0, -10);
        $fqcn = $this->modelNamespace . '\\' . $modelName;

        if (class_exists($fqcn)) {
            return $fqcn;
        }

        return null;
    }

    private function extractSummaryFromDocblock(ReflectionMethod $method): ?string
    {
        $doc = $method->getDocComment();
        if ($doc === false) {
            return null;
        }

        if (preg_match('/\/\*\*\s*\n?\s*\*?\s*(.+?)(\n|\*\/)/s', $doc, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    private function isScalar(string $type): bool
    {
        return in_array($type, ['int', 'string', 'float', 'bool'], true);
    }

    private function mapPhpType(string $type): string
    {
        return match ($type) {
            'int'    => 'integer',
            'float'  => 'number',
            'bool'   => 'boolean',
            'string' => 'string',
            default  => 'string',
        };
    }
}
