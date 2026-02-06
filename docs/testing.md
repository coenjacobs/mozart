# Testing

## Test structure

```
tests/
├── Unit/           # Fast, isolated tests (no external dependencies)
├── Integration/    # Full Mozart flow tests (runs composer + Mozart)
└── Support/        # Shared test utilities
```

**Unit tests** test individual components in isolation. **Integration tests** run the full Mozart flow against real composer.json fixtures.

## Running tests locally

Use the `builder` service for local development. It mounts your working directory, so changes are reflected immediately:

```bash
# Run unit tests
docker compose run --rm builder composer test:unit

# Run integration tests
docker compose run --rm builder composer test:integration

# Run all tests
docker compose run --rm builder composer test:phpunit
```

**Important:** If you add new files or change namespaces, regenerate the autoloader first:

```bash
docker compose run --rm builder composer dump-autoload
```

## Docker services

| Service | Volume Mount | Xdebug | Use Case |
|---------|--------------|--------|----------|
| `builder` | Yes (`.:/mozart/`) | Yes | Local development - changes reflected immediately |
| `actions-tester` | No | No | CI simulation - must rebuild after changes |

**Warning:** The `actions-tester` service copies files at build time. If you edit files and run tests with `actions-tester` without rebuilding, you'll be testing stale code.

To test with `actions-tester` after making changes:

```bash
docker compose build actions-tester
docker compose run --rm actions-tester composer test:phpunit
```

In CI, the workflow pre-builds the `mozart:latest` image, which `actions-tester` then uses directly (no double-build).

The Dockerfile has multiple stages: `base` -> `builder` (for testing) -> `develop` (adds Xdebug) -> `packager` -> `application`. CI builds target the `builder` stage.

## CI checks

The full CI suite (`composer test`) runs these checks in order. All must pass before merge.

| Script | Tool | What it checks |
|---|---|---|
| `test:lint` | phpcs + `composer validate` | Code style (PSR-12), PHP 8.1-8.5 compatibility |
| `test:phpunit` | PHPUnit 10 | Unit tests (all PHP versions) + integration tests (PHP 8.1 + 8.5) |
| `test:phpstan` | PHPStan level 8 | Static type analysis on `src/` |
| `test:phpmd` | PHPMD | Code smells: codesize, cleancode, naming, unused code, design |
| `test:docs` | php-doc-check | Docblock completeness (see below) |

### Docblock requirement

`php-doc-check src` requires **every public method in `src/` to have a docblock**. Missing one fails CI. This is the least obvious check — when adding a new public method, always add a docblock.

### PHPMD suppressions

When a PHPMD violation is intentional, suppress it with an annotation:

```php
/**
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class Replacer { ... }
```

Also use `@SuppressWarnings(PHPMD.UnusedFormalParameter)` for interface-mandated parameters.

## GitHub Actions

The CI workflow (`.github/workflows/main.yml`) runs:

- **Unit tests**: All PHP versions (8.1, 8.2, 8.3, 8.4, 8.5)
- **Integration tests**: PHP 8.1 + 8.5 only (lowest and highest supported)

The PHP version matrix works via the `PHP_VERSION` build arg passed to Docker.

## Adding tests

### Unit tests

Add to `tests/Unit/`, mirroring the `src/` directory structure.

- Namespace: `CoenJacobs\Mozart\Tests\Unit\...`
- Example: a test for `src/Replace/Classmap/NameVisitor.php` goes in `tests/Unit/Replace/Classmap/NameVisitorTest.php`

### Integration tests

Create a directory in `tests/Integration/` with both a test class and a `composer.json` fixture:

```
tests/Integration/MyFeature/
  MyFeatureTest.php     # extends IntegrationTestCase from tests/Support/
  composer.json         # fixture with Mozart config and dependencies
```

- Namespace: `CoenJacobs\Mozart\Tests\Integration\...`

### Shared test utilities

- `tests/Support/IntegrationTestCase.php` — Base class for integration tests with common setup/teardown
- `tests/Support/AstProcessingTestTrait.php` — Helpers for AST-related tests (parse code, assert transformations)

### After adding tests

Run `composer dump-autoload` to update the autoloader so PHPUnit can find new test classes.
