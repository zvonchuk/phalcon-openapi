<?php

namespace PhalconOpenApi;

class SpecAssembler
{
    private const ERROR_SCHEMA_NAME = 'ErrorResponse';
    private const VALIDATION_ERROR_SCHEMA_NAME = 'ValidationErrorResponse';

    public function __construct(
        private RouteCollector $routeCollector,
        private ControllerInspector $inspector,
        private SchemaBuilder $schemaBuilder,
        private array $config = []
    ) {}

    public function generate(): array
    {
        $routes = $this->routeCollector->collect();
        $paths = [];

        foreach ($routes as $route) {
            $info = $this->inspector->inspect(
                $route['controller'],
                $route['action'],
                $route['pathParams']
            );

            if ($info['skip']) {
                continue;
            }

            $operation = $this->buildOperation($info, $route);
            $paths[$route['path']][$route['method']] = $operation;
        }

        $spec = [
            'openapi'    => '3.1.0',
            'info'       => [
                'title'   => $this->config['title'] ?? 'API',
                'version' => $this->config['version'] ?? '1.0.0',
            ],
            'paths'      => $paths,
            'components' => [],
        ];

        if (isset($this->config['description'])) {
            $spec['info']['description'] = $this->config['description'];
        }

        if (isset($this->config['servers'])) {
            $spec['servers'] = $this->config['servers'];
        }

        // Security schemes from config
        if (isset($this->config['security'])) {
            $spec['components']['securitySchemes'] = $this->config['security'];
        }

        $schemas = $this->schemaBuilder->getAllSchemas();
        if (!empty($schemas)) {
            $spec['components']['schemas'] = $schemas;
        }

        if (empty($spec['components'])) {
            unset($spec['components']);
        }

        return $spec;
    }

    private function buildOperation(array $info, array $route): array
    {
        $operation = [];

        if (!empty($info['tags'])) {
            $operation['tags'] = $info['tags'];
        }

        if (!empty($info['summary'])) {
            $operation['summary'] = $info['summary'];
        }

        if (!empty($info['description'])) {
            $operation['description'] = $info['description'];
        }

        if (isset($info['operationId'])) {
            $operation['operationId'] = $info['operationId'];
        }

        // Security from inspector
        if (isset($info['security'])) {
            $operation['security'] = $info['security'];
        }

        // Parameters
        $params = [];
        foreach ($info['parameters'] as $param) {
            $paramSpec = [
                'name'     => $param['name'],
                'in'       => $param['in'],
                'required' => $param['in'] === 'path' ? true : !$param['optional'],
                'schema'   => ['type' => $param['type']],
            ];
            if (isset($param['default'])) {
                $paramSpec['schema']['default'] = $param['default'];
            }
            if (isset($param['nullable']) && $param['nullable']) {
                // OpenAPI 3.1: nullable as type array
                $paramSpec['schema']['type'] = [$param['type'], 'null'];
            }
            $params[] = $paramSpec;
        }
        if (!empty($params)) {
            $operation['parameters'] = $params;
        }

        // Request body
        if ($info['bodyClass'] !== null) {
            $this->schemaBuilder->build($info['bodyClass']);
            $refName = $this->schemaBuilder->getRefName($info['bodyClass']);
            $contentType = $this->schemaBuilder->hasFileUpload($info['bodyClass'])
                ? 'multipart/form-data'
                : 'application/json';
            $operation['requestBody'] = [
                'required' => true,
                'content'  => [
                    $contentType => [
                        'schema' => ['$ref' => '#/components/schemas/' . $refName],
                    ],
                ],
            ];
        }

        // Determine success status code by convention
        $actionName = $route['action'];
        $httpMethod = $route['method'];
        $successCode = $this->inferSuccessCode($actionName, $httpMethod);

        // Responses
        $responses = [];
        $inferredModel = $info['inferredModel'] ?? null;

        // Determine success response: attribute > return type > convention
        if (isset($info['extraResponses'][$successCode])) {
            $dtoClass = $info['extraResponses'][$successCode];
            $this->schemaBuilder->build($dtoClass);
            $refName = $this->schemaBuilder->getRefName($dtoClass);
            $responses[(string) $successCode] = [
                'description' => $this->httpStatusDescription($successCode),
                'content'     => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/' . $refName],
                    ],
                ],
            ];
            unset($info['extraResponses'][$successCode]);
        } elseif ($successCode === 204) {
            // No content for delete
            $responses['204'] = ['description' => 'No Content'];
        } elseif ($info['returnClass'] !== null) {
            $this->schemaBuilder->build($info['returnClass']);
            $refName = $this->schemaBuilder->getRefName($info['returnClass']);
            $responses[(string) $successCode] = [
                'description' => $this->httpStatusDescription($successCode),
                'content'     => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/' . $refName],
                    ],
                ],
            ];
        } elseif ($inferredModel !== null) {
            $responses[(string) $successCode] = $this->buildConventionResponse(
                $inferredModel, $actionName, $successCode, $info['paginated'] ?? null
            );
        } else {
            $responses[(string) $successCode] = [
                'description' => $this->httpStatusDescription($successCode),
            ];
        }

        // Auto 422: if request has a body DTO
        if ($info['bodyClass'] !== null && !isset($info['extraResponses'][422])) {
            $this->ensureValidationErrorSchema();
            $responses['422'] = [
                'description' => 'Validation Error',
                'content'     => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/' . self::VALIDATION_ERROR_SCHEMA_NAME],
                    ],
                ],
            ];
        }

        // Auto 404: if route has path params and no explicit 404
        if (!empty($route['pathParams']) && !isset($info['extraResponses'][404])) {
            $this->ensureErrorSchema();
            $responses['404'] = [
                'description' => 'Not Found',
                'content'     => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/' . self::ERROR_SCHEMA_NAME],
                    ],
                ],
            ];
        }

        // Extra responses (from attributes, excluding success code handled above)
        foreach ($info['extraResponses'] as $code => $dtoClass) {
            $this->schemaBuilder->build($dtoClass);
            $refName = $this->schemaBuilder->getRefName($dtoClass);
            $responses[(string) $code] = [
                'description' => $this->httpStatusDescription($code),
                'content'     => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/' . $refName],
                    ],
                ],
            ];
        }

        $operation['responses'] = $responses;

        return $operation;
    }

    private function inferSuccessCode(string $actionName, string $httpMethod): int
    {
        // createAction or POST → 201
        if (str_starts_with($actionName, 'create') || $httpMethod === 'post') {
            return 201;
        }

        // deleteAction → 204
        if (str_starts_with($actionName, 'delete')) {
            return 204;
        }

        return 200;
    }

    private function buildConventionResponse(
        string $modelClass,
        string $actionName,
        int $statusCode,
        ?array $paginated = null
    ): array {
        // deleteAction → no body (handled by 204 in caller)
        if ($actionName === 'deleteAction') {
            return ['description' => 'No Content'];
        }

        $this->schemaBuilder->build($modelClass);
        $refName = $this->schemaBuilder->getRefName($modelClass);

        // listAction → array of model (optionally paginated)
        if ($actionName === 'listAction') {
            $itemsSchema = ['$ref' => '#/components/schemas/' . $refName];

            if ($paginated !== null) {
                $dataField = $paginated['dataField'] ?? 'data';
                $schema = [
                    'type'       => 'object',
                    'properties' => [
                        $dataField  => ['type' => 'array', 'items' => $itemsSchema],
                        'total'     => ['type' => 'integer'],
                        'page'      => ['type' => 'integer'],
                        'per_page'  => ['type' => 'integer'],
                    ],
                    'required'   => [$dataField, 'total', 'page', 'per_page'],
                ];
            } else {
                $schema = [
                    'type'  => 'array',
                    'items' => $itemsSchema,
                ];
            }

            return [
                'description' => $this->httpStatusDescription($statusCode),
                'content'     => [
                    'application/json' => ['schema' => $schema],
                ],
            ];
        }

        // getAction, createAction, updateAction → single model
        return [
            'description' => $this->httpStatusDescription($statusCode),
            'content'     => [
                'application/json' => [
                    'schema' => ['$ref' => '#/components/schemas/' . $refName],
                ],
            ],
        ];
    }

    private function ensureErrorSchema(): void
    {
        $schemas = $this->schemaBuilder->getAllSchemas();
        if (!isset($schemas[self::ERROR_SCHEMA_NAME])) {
            $this->schemaBuilder->registerSchema(self::ERROR_SCHEMA_NAME, [
                'type'       => 'object',
                'properties' => [
                    'code'    => ['type' => 'integer'],
                    'message' => ['type' => 'string'],
                ],
                'required'   => ['code', 'message'],
            ]);
        }
    }

    private function ensureValidationErrorSchema(): void
    {
        $schemas = $this->schemaBuilder->getAllSchemas();
        if (!isset($schemas[self::VALIDATION_ERROR_SCHEMA_NAME])) {
            $this->schemaBuilder->registerSchema(self::VALIDATION_ERROR_SCHEMA_NAME, [
                'type'       => 'object',
                'properties' => [
                    'code'    => ['type' => 'integer'],
                    'message' => ['type' => 'string'],
                    'errors'  => [
                        'type'  => 'array',
                        'items' => ['type' => 'string'],
                    ],
                ],
                'required'   => ['code', 'message', 'errors'],
            ]);
        }
    }

    private function httpStatusDescription(int $code): string
    {
        return match ($code) {
            200 => 'Success',
            201 => 'Created',
            204 => 'No Content',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            422 => 'Validation Error',
            500 => 'Internal Server Error',
            default => 'Response',
        };
    }
}
