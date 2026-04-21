# phalcon-openapi

[![Tests](https://github.com/zvonchuk/phalcon-openapi/actions/workflows/tests.yml/badge.svg)](https://github.com/zvonchuk/phalcon-openapi/actions)
[![Latest Stable Version](https://img.shields.io/packagist/v/zvonchuk/phalcon-openapi.svg)](https://packagist.org/packages/zvonchuk/phalcon-openapi)
[![PHP Version](https://img.shields.io/packagist/php-v/zvonchuk/phalcon-openapi.svg)](https://www.php.net/)
[![License](https://img.shields.io/packagist/l/zvonchuk/phalcon-openapi.svg)](LICENSE)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%206-brightgreen.svg)](https://phpstan.org/)

Automatic **OpenAPI 3.1** spec generation for Phalcon PHP applications.

Reads your existing code via reflection and Phalcon APIs — no annotations, no YAML, no manual work. Unlike `zircote/swagger-php`, which requires a parallel layer of `#[OA\...]` attributes, this package infers everything from your routes, controllers, models, and DTOs.

## Why This Package

| | swagger-php | phalcon-openapi |
|---|---|---|
| Routes | Duplicated in `#[OA\Get(path:...)]` | Read from Phalcon Router |
| Schemas | Manual `#[OA\Schema]` + `#[OA\Property]` | Built from DTOs and Models |
| Validation | Docs only, not enforced | Same attributes for docs AND runtime |
| Status codes | Manual per-endpoint | Convention-based (201, 204, 422, 404) |
| Setup | DocsController + HTML + routes | Two lines of code |

## Requirements

- PHP 8.1+
- Phalcon 5.x

## Installation

```bash
composer require zvonchuk/phalcon-openapi
```

## Quick Start

```php
use PhalconOpenApi\OpenApiModule;

$module = new OpenApiModule([
    'title'   => 'My API',
    'version' => '1.0.0',
]);
$module->registerServices($di);
```

Two endpoints are registered automatically:
- `GET /api/openapi.json` — OpenAPI 3.1 JSON spec
- `GET /api/docs` — Swagger UI

## How It Works

The package automatically reads:
- **Router** — all registered routes, HTTP methods, path patterns
- **Model MetaData** — column types, nullable, primary keys
- **Reflection** — controller action parameters, return types, docblocks
- **PHP 8 Attributes** — optional tags, hidden endpoints, extra responses, security

### Convention-Based Inference

Zero annotations needed for standard CRUD:

```php
class UserController extends ApiController
{
    // GET /users → 200 with array of User, operationId: listUsers
    public function listAction(int $page = 1, int $limit = 20) { }

    // POST /users → 201 Created + 422 Validation Error, operationId: createUser
    public function createAction(CreateUserRequest $body) { }

    // GET /users/{id} → 200 + 404 Not Found, operationId: getUser
    public function getAction(int $id) { }

    // DELETE /users/{id} → 204 No Content + 404, operationId: deleteUser
    public function deleteAction(int $id) { }
}
```

The spec generator infers:
- **Status codes**: `create` → 201, `delete` → 204, others → 200
- **422 Validation Error**: auto-added when endpoint has a DTO body parameter
- **404 Not Found**: auto-added when route has path parameters
- **operationId**: generated from controller + action name
- **Tags**: from controller name (`UserController` → `Users`)
- **Schemas**: from Phalcon Model metadata or DTO class properties

## Attributes Reference

All attributes are optional — use only when conventions aren't enough.

### Endpoint Attributes

```php
use PhalconOpenApi\Attribute\{ApiTag, ApiIgnore, ApiResponse, ApiDescription, ApiSecurity, ApiPaginated};

#[ApiTag('Users')]              // Group endpoints (class or method level)
#[ApiSecurity('bearerAuth')]    // Require auth (class or method level)
class UserController extends ApiController
{
    #[ApiDescription(
        summary: 'List all users',
        description: 'Returns a paginated list of users with optional filtering'
    )]
    #[ApiPaginated]             // Wraps response in {data, total, page, per_page}
    public function listAction(int $page = 1) { }

    #[ApiIgnore]                // Hide from spec
    public function internalAction() { }

    #[ApiResponse(409, ConflictResponse::class)]  // Extra response code
    public function createAction(CreateUserRequest $body) { }
}
```

### Validation Attributes

Used for both **runtime validation** (via `DtoValidator`) and **OpenAPI schema generation**:

```php
use PhalconOpenApi\Attribute\{Email, Min, Max, StringLength, Format, Pattern, Enum, Url, NotBlank};

class CreateUserRequest
{
    #[NotBlank]
    #[StringLength(min: 1, max: 255)]
    public string $name;

    #[Email]
    public string $email;

    public ?string $phone = null;       // nullable → type: ["string", "null"]

    #[Min(1), Max(150)]
    public int $age;

    #[Enum(['active', 'inactive'])]
    public string $status = 'active';   // → enum in OpenAPI schema

    #[Url]
    public ?string $website = null;     // → format: uri in schema

    #[Format('date')]
    public ?string $birthDate = null;   // → format: date in schema

    #[Pattern('/^\+\d{10,15}$/')]       // PCRE delimiters stripped for OpenAPI
    public ?string $mobile = null;
}
```

| Attribute | Validates | OpenAPI Schema |
|---|---|---|
| `#[Email]` | Valid email format | `format: email` |
| `#[StringLength(min: 1, max: 255)]` | String length bounds | `minLength`, `maxLength` |
| `#[Min(1)]`, `#[Max(150)]` | Numeric range | `minimum`, `maximum` |
| `#[Enum(['a', 'b'])]` | Allowed values | `enum: ["a", "b"]` |
| `#[Url]` | Valid URL | `format: uri` |
| `#[NotBlank]` | Rejects whitespace-only | `minLength: 1` |
| `#[Format('date')]` | Date/datetime/uuid/uri | `format: date` |
| `#[Pattern('/regex/')]` | PCRE regex match | `pattern: regex` |

### Nested DTOs and Typed Arrays

Nested objects are validated recursively with dot-notation error paths:

```php
class CreateOrderRequest
{
    #[StringLength(min: 1)]
    public string $orderNumber;

    public AddressDto $shippingAddress;  // → $ref + recursive validation

    /** @var OrderItemDto[] */
    public array $items = [];            // → array with $ref + per-item validation
}

class AddressDto
{
    #[NotBlank]
    public string $street;

    #[NotBlank]
    public string $city;

    #[Pattern('/^\d{5}$/')]
    public string $zip;
}
```

Validation errors for nested objects use dot-notation:
```json
{
    "code": 422,
    "message": "Validation failed",
    "errors": [
        "shippingAddress.city is required",
        "items[0].zip must match pattern /^\\d{5}$/"
    ]
}
```

## Security Configuration

```php
$module = new OpenApiModule([
    'title'   => 'My API',
    'version' => '1.0.0',
    'security' => [
        'bearerAuth' => [
            'type'         => 'http',
            'scheme'       => 'bearer',
            'bearerFormat' => 'JWT',
        ],
    ],
]);
```

Then annotate controllers or methods with `#[ApiSecurity('bearerAuth')]`.

## Base Controller

Extend `ApiController` for automatic JSON body parsing, DTO validation,
and convenience helpers:

```php
use PhalconOpenApi\ApiController;

class UserController extends ApiController
{
    public function getAction(int $id)
    {
        $user = User::findFirst($id);
        if (!$user) {
            return $this->notFound('User not found');
        }
        return $this->json($user);
    }

    public function createAction(CreateUserRequest $body)
    {
        // $body is already validated and hydrated automatically
        $user = new User();
        $user->assign((array) $body);
        $user->save();
        return $this->json($user, 201);
    }
}
```

## Configuration Options

```php
$module = new OpenApiModule([
    'title'          => 'My API',           // required
    'version'        => '1.0.0',            // required
    'description'    => 'API description',  // optional
    'modelNamespace' => 'App\\Models',      // enables convention-based model inference
    'servers'        => [                   // optional
        ['url' => 'https://api.example.com'],
    ],
    'security'       => [ /* ... */ ],      // optional, see Security section
]);
```

## Demo Application

See [phalcon-swagger](https://github.com/zvonchuk/phalcon-swagger) for a complete working example with Docker, MySQL, and CRUD controllers.

## Running Tests

```bash
vendor/bin/phpunit
```

## License

MIT
