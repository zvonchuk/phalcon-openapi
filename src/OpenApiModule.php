<?php

namespace PhalconOpenApi;

use Phalcon\Di\DiInterface;
use Phalcon\Mvc\ModuleDefinitionInterface;

class OpenApiModule implements ModuleDefinitionInterface
{
    public function __construct(
        private array $config = []
    ) {}

    public function registerAutoloaders(DiInterface $container = null): void
    {
        // Autoloading handled by Composer
    }

    public function registerServices(DiInterface $container): void
    {
        $config = $this->config;

        $container->setShared('openApiGenerator', function () use ($container, $config) {
            $router = $container->getShared('router');
            $metaData = null;

            if ($container->has('modelsMetadata')) {
                $metaData = $container->getShared('modelsMetadata');
            }

            return new SpecAssembler(
                new RouteCollector($router),
                new ControllerInspector($config['modelNamespace'] ?? ''),
                new SchemaBuilder($metaData),
                $config
            );
        });

        // Register docs routes
        /** @var \Phalcon\Mvc\Router $router */
        $router = $container->getShared('router');
        $router->addGet('/api/openapi.json', [
            'namespace'  => 'PhalconOpenApi',
            'controller' => 'docs',
            'action'     => 'spec',
        ]);
        $router->addGet('/api/docs', [
            'namespace'  => 'PhalconOpenApi',
            'controller' => 'docs',
            'action'     => 'docs',
        ]);
    }
}
