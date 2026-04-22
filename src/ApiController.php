<?php

namespace PhalconOpenApi;

use Phalcon\Mvc\Controller;
use Phalcon\Mvc\Model;
use Phalcon\Mvc\Model\ResultsetInterface;

class ApiController extends Controller
{
    private DtoValidator $dtoValidator;

    public function onConstruct(): void
    {
        $this->dtoValidator = new DtoValidator();
    }

    /**
     * Auto-inject DTO body parameter before action executes.
     */
    public function beforeExecuteRoute($dispatcher)
    {
        $actionMethod = $dispatcher->getActiveMethod();
        if (!method_exists($this, $actionMethod)) {
            return;
        }

        $ref = new \ReflectionMethod($this, $actionMethod);
        $params = $dispatcher->getParams();

        foreach ($ref->getParameters() as $index => $param) {
            $type = $param->getType();
            if (!$type instanceof \ReflectionNamedType) {
                continue;
            }

            $typeName = $type->getName();

            // Skip scalars and Phalcon types
            if (in_array($typeName, ['int', 'string', 'float', 'bool', 'array'], true)) {
                continue;
            }
            if (str_starts_with($typeName, 'Phalcon\\')) {
                continue;
            }

            // This is a DTO parameter — parse JSON body
            $rawBody = $this->request->getRawBody();
            $data = json_decode($rawBody, true);

            if (!is_array($data)) {
                throw new \RuntimeException(json_encode([
                    'code'    => 400,
                    'message' => 'Invalid JSON body',
                ]), 400);
            }

            // Validate
            $errors = $this->dtoValidator->validate($typeName, $data);
            if (!empty($errors)) {
                throw new \RuntimeException(json_encode([
                    'code'    => 422,
                    'message' => 'Validation failed',
                    'errors'  => $errors,
                ]), 422);
            }

            // Hydrate DTO and inject into dispatcher params
            $dto = $this->dtoValidator->hydrate($typeName, $data);
            $params[$param->getName()] = $dto;
            $dispatcher->setParams($params);
            break; // only one body parameter
        }
    }

    /**
     * Return JSON response.
     */
    protected function json(mixed $data = null, int $status = 200): \Phalcon\Http\ResponseInterface
    {
        $this->response->setStatusCode($status);

        if ($data === null && $status === 204) {
            return $this->response;
        }

        if ($data instanceof Model) {
            $data = $data->toArray();
        } elseif ($data instanceof ResultsetInterface) {
            $data = $data->toArray();
        } elseif (is_object($data)) {
            $data = (array) $data;
        }

        $this->response->setJsonContent($data);
        return $this->response;
    }

    /**
     * Return 404 Not Found.
     */
    protected function notFound(string $message = 'Not found'): \Phalcon\Http\ResponseInterface
    {
        return $this->error(404, $message);
    }

    /**
     * Return error response.
     */
    protected function error(int $code, string $message): \Phalcon\Http\ResponseInterface
    {
        $this->response->setStatusCode($code);
        $this->response->setJsonContent([
            'code'    => $code,
            'message' => $message,
        ]);
        return $this->response;
    }
}
