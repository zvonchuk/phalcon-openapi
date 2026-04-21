<?php

namespace PhalconOpenApi;

class ErrorHandler
{
    /**
     * Register error and exception handlers that always return JSON.
     */
    public static function register(): void
    {
        set_exception_handler(function (\Throwable $e) {
            $code = $e->getCode();
            if ($code < 400 || $code > 599) {
                $code = 500;
            }

            http_response_code($code);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'code'    => $code,
                'message' => $e->getMessage(),
            ]);
        });

        set_error_handler(function (int $severity, string $message, string $file, int $line) {
            throw new \ErrorException($message, 500, $severity, $file, $line);
        });
    }
}
