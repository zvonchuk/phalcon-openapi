<?php

namespace PhalconOpenApi;

use Phalcon\Db\Column;
use Phalcon\Mvc\Model;
use Phalcon\Mvc\Model\MetaDataInterface;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

class SchemaBuilder
{
    /** @var array<string, array> */
    private array $schemas = [];

    private ?MetaDataInterface $metaData;

    public function __construct(?MetaDataInterface $metaData = null)
    {
        $this->metaData = $metaData;
    }

    public function build(string $className): array
    {
        $shortName = (new ReflectionClass($className))->getShortName();

        if (isset($this->schemas[$shortName])) {
            return $this->schemas[$shortName];
        }

        if (is_subclass_of($className, Model::class) && $this->metaData !== null) {
            $schema = $this->buildFromModel($className);
        } else {
            $schema = $this->buildFromDto($className);
        }

        $this->schemas[$shortName] = $schema;
        return $schema;
    }

    /**
     * @return array<string, array>
     */
    public function getAllSchemas(): array
    {
        return $this->schemas;
    }

    public function getRefName(string $className): string
    {
        return (new ReflectionClass($className))->getShortName();
    }

    public function registerSchema(string $name, array $schema): void
    {
        $this->schemas[$name] = $schema;
    }

    private function buildFromDto(string $className): array
    {
        $ref = new ReflectionClass($className);
        $properties = [];
        $required = [];

        foreach ($ref->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            $type = $prop->getType();
            $propSchema = [];

            if ($type instanceof ReflectionNamedType) {
                $typeName = $type->getName();

                if ($this->isNestedDto($typeName)) {
                    // Nested DTO → $ref
                    $this->build($typeName);
                    $refName = $this->getRefName($typeName);
                    $propSchema = ['$ref' => '#/components/schemas/' . $refName];
                } elseif ($typeName === 'array') {
                    // Check @var for typed arrays (e.g. @var ClassName[])
                    $itemClass = $this->extractArrayItemClass($prop);
                    if ($itemClass !== null) {
                        $this->build($itemClass);
                        $refName = $this->getRefName($itemClass);
                        $propSchema = [
                            'type'  => 'array',
                            'items' => ['$ref' => '#/components/schemas/' . $refName],
                        ];
                    } else {
                        $propSchema = ['type' => 'array'];
                    }
                } else {
                    $propSchema = $this->phpTypeToJsonSchema($typeName);
                }

                // Read validation attributes for OpenAPI schema
                foreach ($prop->getAttributes() as $attr) {
                    $attrName = $attr->getName();
                    if ($attrName === \PhalconOpenApi\Attribute\Email::class) {
                        $propSchema['format'] = 'email';
                    } elseif ($attrName === \PhalconOpenApi\Attribute\Min::class) {
                        $propSchema['minimum'] = $attr->newInstance()->value;
                    } elseif ($attrName === \PhalconOpenApi\Attribute\Max::class) {
                        $propSchema['maximum'] = $attr->newInstance()->value;
                    } elseif ($attrName === \PhalconOpenApi\Attribute\StringLength::class) {
                        $instance = $attr->newInstance();
                        if ($instance->min !== null) {
                            $propSchema['minLength'] = $instance->min;
                        }
                        if ($instance->max !== null) {
                            $propSchema['maxLength'] = $instance->max;
                        }
                    } elseif ($attrName === \PhalconOpenApi\Attribute\Format::class) {
                        $propSchema['format'] = $attr->newInstance()->format;
                    } elseif ($attrName === \PhalconOpenApi\Attribute\Pattern::class) {
                        $propSchema['pattern'] = $this->stripPcreDelimiters($attr->newInstance()->regex);
                    } elseif ($attrName === \PhalconOpenApi\Attribute\Enum::class) {
                        $propSchema['enum'] = $attr->newInstance()->values;
                    } elseif ($attrName === \PhalconOpenApi\Attribute\Url::class) {
                        $propSchema['format'] = 'uri';
                    } elseif ($attrName === \PhalconOpenApi\Attribute\NotBlank::class) {
                        $propSchema['minLength'] = max($propSchema['minLength'] ?? 0, 1);
                    }
                }

                // OpenAPI 3.1: nullable as type array
                if ($type->allowsNull()) {
                    if (isset($propSchema['type']) && is_string($propSchema['type'])) {
                        $propSchema['type'] = [$propSchema['type'], 'null'];
                    }
                }
            }

            $properties[$prop->getName()] = $propSchema;

            if (!$prop->hasDefaultValue() && ($type === null || !$type->allowsNull())) {
                $required[] = $prop->getName();
            }
        }

        $schema = [
            'type'       => 'object',
            'properties' => $properties,
        ];

        if (!empty($required)) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    /**
     * Strip PCRE delimiters from a regex for OpenAPI (ECMA-262 format).
     * Converts "/^foo$/" → "^foo$"
     */
    private function stripPcreDelimiters(string $regex): string
    {
        if (strlen($regex) >= 2) {
            $delimiter = $regex[0];
            // Common PCRE delimiters: / # ~ ! @ %
            if (!ctype_alnum($delimiter) && $delimiter !== '\\') {
                $lastPos = strrpos($regex, $delimiter);
                if ($lastPos > 0) {
                    return substr($regex, 1, $lastPos - 1);
                }
            }
        }
        return $regex;
    }

    private function buildFromModel(string $className): array
    {
        /** @var Model $model */
        $model = new $className();
        $metaData = $this->metaData;

        $dataTypes = $metaData->getDataTypes($model);
        $notNull = $metaData->getNotNullAttributes($model);
        $primaryKeys = $metaData->getPrimaryKeyAttributes($model);
        $columnMap = $metaData->getColumnMap($model);

        $properties = [];
        $required = [];

        foreach ($dataTypes as $column => $type) {
            $propName = $columnMap[$column] ?? $column;
            $propSchema = $this->phalconTypeToJsonSchema($type);

            if (in_array($column, $primaryKeys, true)) {
                $propSchema['readOnly'] = true;
            }

            $properties[$propName] = $propSchema;

            if (in_array($column, $notNull, true) && !in_array($column, $primaryKeys, true)) {
                $required[] = $propName;
            }
        }

        $schema = [
            'type'       => 'object',
            'properties' => $properties,
        ];

        if (!empty($required)) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    private function isNestedDto(string $typeName): bool
    {
        if (in_array($typeName, ['int', 'float', 'string', 'bool', 'array', 'object', 'mixed'], true)) {
            return false;
        }
        if (str_starts_with($typeName, 'Phalcon\\')) {
            return false;
        }
        return class_exists($typeName) && !is_subclass_of($typeName, Model::class);
    }

    private function extractArrayItemClass(ReflectionProperty $prop): ?string
    {
        $doc = $prop->getDocComment();
        if ($doc === false) {
            return null;
        }

        // Match @var ClassName[] or @var \Full\ClassName[]
        if (preg_match('/@var\s+([\w\\\\]+)\[\]/', $doc, $matches)) {
            $className = $matches[1];

            // Try fully qualified
            if (class_exists($className)) {
                return $className;
            }

            // Try relative to the declaring class namespace
            $declaringClass = $prop->getDeclaringClass();
            $ns = $declaringClass->getNamespaceName();
            if ($ns !== '' && class_exists($ns . '\\' . $className)) {
                return $ns . '\\' . $className;
            }
        }

        return null;
    }

    private function phpTypeToJsonSchema(string $typeName): array
    {
        return match ($typeName) {
            'int'    => ['type' => 'integer'],
            'float'  => ['type' => 'number'],
            'bool'   => ['type' => 'boolean'],
            'string' => ['type' => 'string'],
            'array'  => ['type' => 'array'],
            default  => ['type' => 'object'],
        };
    }

    private function phalconTypeToJsonSchema(int $type): array
    {
        return match ($type) {
            Column::TYPE_INTEGER, Column::TYPE_BIGINTEGER => ['type' => 'integer'],
            Column::TYPE_FLOAT, Column::TYPE_DOUBLE, Column::TYPE_DECIMAL => ['type' => 'number'],
            Column::TYPE_BOOLEAN => ['type' => 'boolean'],
            Column::TYPE_DATE => ['type' => 'string', 'format' => 'date'],
            Column::TYPE_DATETIME, Column::TYPE_TIMESTAMP => ['type' => 'string', 'format' => 'date-time'],
            Column::TYPE_JSON => ['type' => 'object'],
            default => ['type' => 'string'],
        };
    }
}
