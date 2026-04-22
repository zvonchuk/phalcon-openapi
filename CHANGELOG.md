# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [1.0.0] - 2026-04-21

### Added
- **Core**: RouteCollector reads routes from Phalcon Router
- **Core**: ControllerInspector extracts parameters, return types, attributes via reflection
- **Core**: SchemaBuilder builds JSON Schema from DTOs and Phalcon Models
- **Core**: SpecAssembler orchestrates full OpenAPI 3.1 document generation
- **Core**: DocsController serves OpenAPI JSON and Swagger UI
- **Core**: OpenApiModule for two-line Phalcon DI integration
- **Conventions**: Auto-inferred status codes (201 for create, 204 for delete)
- **Conventions**: Auto 422 Validation Error when endpoint has DTO body
- **Conventions**: Auto 404 Not Found when route has path parameters
- **Conventions**: operationId from controller + action name
- **Conventions**: Tags from controller name (UserController → Users)
- **Attributes**: ApiTag, ApiIgnore, ApiResponse, ApiDescription
- **Attributes**: ApiSecurity for auth schemes on class/method level
- **Attributes**: ApiPaginated for list response wrapping
- **Validation**: Email, StringLength, Min, Max, Format, Pattern
- **Validation**: Enum, Url, NotBlank
- **Validation**: Recursive nested DTO validation with dot-notation errors
- **Validation**: Typed array validation via `@var ClassName[]`
- **Base**: ApiController with auto DTO parsing, validation, and JSON helpers
- **Base**: DtoValidator for runtime validation + hydration
- **Base**: ErrorHandler for consistent JSON error responses
