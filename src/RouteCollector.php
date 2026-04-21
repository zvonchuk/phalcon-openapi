<?php

namespace PhalconOpenApi;

use Phalcon\Mvc\Router;

class RouteCollector
{
    public function __construct(
        private Router $router
    ) {}

    /**
     * @return array<int, array{path: string, pathParams: string[], method: string, controller: string, action: string}>
     */
    public function collect(): array
    {
        $result = [];
        $defaults = $this->router->getDefaults();
        $defaultNamespace = $defaults['namespace'] ?? '';

        foreach ($this->router->getRoutes() as $route) {
            $paths = $route->getPaths();
            if (!isset($paths['controller'])) {
                continue;
            }

            $namespace = $paths['namespace'] ?? $defaultNamespace;

            // Skip internal phalcon-openapi routes (DocsController)
            if ($namespace === 'PhalconOpenApi') {
                continue;
            }

            $controllerName = ucfirst($paths['controller']) . 'Controller';
            $fqcn = $namespace ? $namespace . '\\' . $controllerName : $controllerName;
            $action = ($paths['action'] ?? 'index') . 'Action';

            $pattern = $route->getPattern();
            [$openApiPath, $pathParams] = $this->convertPattern($pattern, $paths);

            $httpMethods = $route->getHttpMethods();
            if ($httpMethods === null) {
                $httpMethods = ['GET'];
            }
            if (is_string($httpMethods)) {
                $httpMethods = [$httpMethods];
            }

            foreach ($httpMethods as $method) {
                $result[] = [
                    'path'       => $openApiPath,
                    'pathParams' => $pathParams,
                    'method'     => strtolower($method),
                    'controller' => $fqcn,
                    'action'     => $action,
                ];
            }
        }

        return $result;
    }

    /**
     * @return array{0: string, 1: string[]}
     */
    private function convertPattern(string $pattern, array $routePaths): array
    {
        $pathParams = [];

        // Named regex groups: (?P<name>...)
        $converted = preg_replace_callback(
            '/\(\?P<(\w+)>[^)]+\)/',
            function (array $matches) use (&$pathParams): string {
                $pathParams[] = $matches[1];
                return '{' . $matches[1] . '}';
            },
            $pattern
        );

        // Unnamed regex groups: ([0-9]+) etc. — get name from route paths
        $unnamedIndex = 1;
        $converted = preg_replace_callback(
            '/\([^?][^)]*\)/',
            function (array $matches) use ($routePaths, &$unnamedIndex, &$pathParams): string {
                $name = $routePaths[$unnamedIndex] ?? 'param' . $unnamedIndex;
                $unnamedIndex++;
                $pathParams[] = $name;
                return '{' . $name . '}';
            },
            $converted
        );

        // Already OpenAPI format: {name} — extract param names
        preg_match_all('/\{(\w+)\}/', $converted, $bracketMatches);
        foreach ($bracketMatches[1] as $name) {
            if (!in_array($name, $pathParams, true)) {
                $pathParams[] = $name;
            }
        }

        return [$converted, $pathParams];
    }
}
