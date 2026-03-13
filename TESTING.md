# Testing

## Overview

- **Framework:** PHPUnit (PSR-compliant).
- **Coverage target:** 100% line coverage for all code in `src` except `src/Api` (see below).
- **CI:** Tests and coverage run on every push/PR; build fails if tests fail or line coverage is below the configured threshold.

## Scope

### In scope (must be covered)

- **Services** (`src/Service`): All public methods and branches (happy path, guard clauses, errors).
- **Domain** (`src/Domain`): Entities, value objects, repositories (repositories tested with mocked PDO).
- **HTTP layer** (`src/Http`): Kernel, middleware, request context.
- **Infrastructure** (`src/Infrastructure`): Payment/billing abstractions (with mocks where they call external APIs).
- **Application** (`src/Application`): Bootstrap and container wiring where it has behaviour.

### Excluded from coverage

- **`src/Api`:** Controllers are kept thin and are exercised indirectly via integration tests; business logic lives in services and is fully covered there.
- **Interfaces:** No executable statements.
- **`public/index.php`:** Entry point; not included in coverage.

## Running tests

```bash
composer install
vendor/bin/phpunit
```

With coverage (Clover XML, used by CI):

```bash
vendor/bin/phpunit --coverage-clover=coverage.xml
```

Enforce minimum line coverage (e.g. 100%):

```bash
php bin/check-coverage.php coverage.xml 100
```

## Structure

- **Unit tests:** `tests/Unit/` — one test class per production class where possible (e.g. `Service/FooService` → `Unit/Service/FooServiceTest.php`).
- **Integration tests:** `tests/Integration/` — end-to-end flows (e.g. health endpoint, auth, key API flows).

## Conventions

- `declare(strict_types=1);` at the top of every test file.
- Use dependency injection and mocks (PHPUnit `createMock`) for repositories, external services, and PDO.
- Prefer positional placeholders and avoid `bindValue`/`bindParam` in production code; tests should not rely on superglobals.
- Descriptive test names, e.g. `testCheckoutThrowsWhenCartEmpty`, `testFindByIdReturnsNullWhenNotFound`.

## Adding tests for new code

1. Add a test class under `tests/Unit/` mirroring the namespace of the class under test (e.g. `src/Service/NewService.php` → `tests/Unit/Service/NewServiceTest.php`).
2. Cover all public methods and meaningful branches (null checks, validation, error paths).
3. Run `vendor/bin/phpunit --coverage-clover=coverage.xml` and `php bin/check-coverage.php coverage.xml 100` locally before pushing to avoid CI failures.
