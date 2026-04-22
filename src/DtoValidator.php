<?php

namespace PhalconOpenApi;

use PhalconOpenApi\Attribute\Email;
use PhalconOpenApi\Attribute\Enum;
use PhalconOpenApi\Attribute\Format;
use PhalconOpenApi\Attribute\Max;
use PhalconOpenApi\Attribute\Min;
use PhalconOpenApi\Attribute\NotBlank;
use PhalconOpenApi\Attribute\Pattern;
use PhalconOpenApi\Attribute\StringLength;
use PhalconOpenApi\Attribute\Url;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

class DtoValidator
{
    /**
     * Validate data against DTO class definition.
     * Returns array of error messages, empty if valid.
     *
     * @return string[]
     */
    public function validate(string $dtoClass, array $data): array
    {
        $errors = [];
        $ref = new ReflectionClass($dtoClass);

        foreach ($ref->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            $name = $prop->getName();
            $type = $prop->getType();
            $exists = array_key_exists($name, $data);
            $value = $data[$name] ?? null;

            // Required check: no default + not nullable = required
            if (!$exists && !$prop->hasDefaultValue()) {
                if ($type instanceof ReflectionNamedType && $type->allowsNull()) {
                    continue;
                }
                $errors[] = "{$name} is required";
                continue;
            }

            if (!$exists) {
                continue;
            }

            // Type check
            if ($type instanceof ReflectionNamedType && $value !== null) {
                $typeError = $this->checkType($name, $value, $type->getName());
                if ($typeError !== null) {
                    $errors[] = $typeError;
                    continue; // skip attribute validation if type is wrong
                }
            }

            // Nullable check
            if ($value === null) {
                if ($type instanceof ReflectionNamedType && !$type->allowsNull()) {
                    $errors[] = "{$name} must not be null";
                }
                continue;
            }

            // Attribute validation
            foreach ($prop->getAttributes() as $attr) {
                $attrError = $this->validateAttribute($name, $value, $attr);
                if ($attrError !== null) {
                    $errors[] = $attrError;
                }
            }

            // Recursive nested DTO validation
            if ($type instanceof ReflectionNamedType && is_array($value)) {
                $typeName = $type->getName();
                if ($this->isDto($typeName)) {
                    $nestedErrors = $this->validate($typeName, $value);
                    foreach ($nestedErrors as $nestedError) {
                        $errors[] = "{$name}.{$nestedError}";
                    }
                }
            }

            // Typed array validation via @var ClassName[]
            if ($type instanceof ReflectionNamedType && $type->getName() === 'array' && is_array($value)) {
                $itemClass = $this->extractArrayItemClass($prop);
                if ($itemClass !== null) {
                    foreach ($value as $index => $item) {
                        if (is_array($item)) {
                            $itemErrors = $this->validate($itemClass, $item);
                            foreach ($itemErrors as $itemError) {
                                $errors[] = "{$name}[{$index}].{$itemError}";
                            }
                        }
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Create and populate DTO from data array.
     * Recursively hydrates nested DTOs and typed arrays.
     */
    public function hydrate(string $dtoClass, array $data): object
    {
        $dto = new $dtoClass();
        $ref = new ReflectionClass($dtoClass);

        foreach ($ref->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            $name = $prop->getName();
            if (array_key_exists($name, $data)) {
                $value = $data[$name];
                $type = $prop->getType();

                // Recursively hydrate nested DTO
                if ($type instanceof ReflectionNamedType && is_array($value)) {
                    $typeName = $type->getName();
                    if ($this->isDto($typeName)) {
                        $value = $this->hydrate($typeName, $value);
                    } elseif ($typeName === 'array') {
                        $itemClass = $this->extractArrayItemClass($prop);
                        if ($itemClass !== null) {
                            $value = array_map(
                                fn($item) => is_array($item) ? $this->hydrate($itemClass, $item) : $item,
                                $value
                            );
                        }
                    }
                }

                $prop->setValue($dto, $value);
            }
        }

        return $dto;
    }

    private function isDto(string $typeName): bool
    {
        if (in_array($typeName, ['int', 'float', 'string', 'bool', 'array', 'object', 'mixed'], true)) {
            return false;
        }
        return class_exists($typeName);
    }

    private function extractArrayItemClass(ReflectionProperty $prop): ?string
    {
        $doc = $prop->getDocComment();
        if ($doc === false) {
            return null;
        }

        if (preg_match('/@var\s+([\w\\\\]+)\[\]/', $doc, $matches)) {
            $className = $matches[1];
            if (class_exists($className)) {
                return $className;
            }
            $ns = $prop->getDeclaringClass()->getNamespaceName();
            if ($ns !== '' && class_exists($ns . '\\' . $className)) {
                return $ns . '\\' . $className;
            }
        }

        return null;
    }

    private function checkType(string $name, mixed $value, string $expectedType): ?string
    {
        $valid = match ($expectedType) {
            'int' => is_int($value),
            'float' => is_int($value) || is_float($value),
            'string' => is_string($value),
            'bool' => is_bool($value),
            'array' => is_array($value),
            default => $this->isDto($expectedType) ? is_array($value) : true,
        };

        if (!$valid) {
            return "{$name} must be of type {$expectedType}";
        }

        return null;
    }

    private function validateAttribute(string $name, mixed $value, \ReflectionAttribute $attr): ?string
    {
        $attrName = $attr->getName();

        if ($attrName === Email::class) {
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return "{$name} must be a valid email address";
            }
        }

        if ($attrName === Min::class) {
            $instance = $attr->newInstance();
            if ($value < $instance->value) {
                return "{$name} must be at least {$instance->value}";
            }
        }

        if ($attrName === Max::class) {
            $instance = $attr->newInstance();
            if ($value > $instance->value) {
                return "{$name} must be at most {$instance->value}";
            }
        }

        if ($attrName === StringLength::class) {
            $instance = $attr->newInstance();
            $len = mb_strlen($value);
            if ($instance->min !== null && $len < $instance->min) {
                return "{$name} must be at least {$instance->min} characters";
            }
            if ($instance->max !== null && $len > $instance->max) {
                return "{$name} must be at most {$instance->max} characters";
            }
        }

        if ($attrName === Format::class) {
            $instance = $attr->newInstance();
            $formatError = $this->checkFormat($name, $value, $instance->format);
            if ($formatError !== null) {
                return $formatError;
            }
        }

        if ($attrName === Pattern::class) {
            $instance = $attr->newInstance();
            if (!preg_match($instance->regex, $value)) {
                return "{$name} must match pattern {$instance->regex}";
            }
        }

        if ($attrName === Enum::class) {
            $instance = $attr->newInstance();
            if (!in_array($value, $instance->values, true)) {
                $allowed = implode(', ', $instance->values);
                return "{$name} must be one of: {$allowed}";
            }
        }

        if ($attrName === Url::class) {
            if (!filter_var($value, FILTER_VALIDATE_URL)) {
                return "{$name} must be a valid URL";
            }
        }

        if ($attrName === NotBlank::class) {
            if (is_string($value) && trim($value) === '') {
                return "{$name} must not be blank";
            }
        }

        return null;
    }

    private function checkFormat(string $name, mixed $value, string $format): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $valid = match ($format) {
            'date' => (bool) \DateTime::createFromFormat('Y-m-d', $value),
            'date-time' => (bool) \DateTime::createFromFormat('Y-m-d\TH:i:s', $value)
                || (bool) \DateTime::createFromFormat(\DateTimeInterface::RFC3339, $value),
            'uuid' => (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value),
            'url', 'uri' => (bool) filter_var($value, FILTER_VALIDATE_URL),
            default => true,
        };

        if (!$valid) {
            return "{$name} must be a valid {$format}";
        }

        return null;
    }
}
