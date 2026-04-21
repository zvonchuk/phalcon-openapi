# Contributing

Thank you for considering contributing to phalcon-openapi!

## Development Setup

```bash
git clone https://github.com/zvonchuk/phalcon-openapi.git
cd phalcon-openapi
composer install
```

You need PHP 8.1+ with the Phalcon 5 extension installed.

## Running Tests

```bash
vendor/bin/phpunit
```

## Code Quality

Before submitting a PR, make sure:

```bash
# Tests pass
vendor/bin/phpunit

# Static analysis passes
vendor/bin/phpstan analyse
```

## Pull Request Process

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/my-feature`)
3. Write tests for new functionality
4. Ensure all tests pass
5. Commit with a clear message
6. Push to your fork and open a Pull Request

## Commit Messages

Follow [Conventional Commits](https://www.conventionalcommits.org/):

- `feat:` — new feature
- `fix:` — bug fix
- `docs:` — documentation only
- `test:` — adding or updating tests
- `refactor:` — code change that neither fixes a bug nor adds a feature

## Adding New Validation Attributes

Each validation attribute must work in two places:

1. **SchemaBuilder** — emit the correct OpenAPI schema property
2. **DtoValidator** — validate the value at runtime

This is the single-source-of-truth principle. One attribute, two consumers.

See existing attributes in `src/Attribute/` for examples.

## Reporting Bugs

Open an issue with:
- PHP and Phalcon version
- Minimal code to reproduce
- Expected vs actual behavior
