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

The test suites are exposed as Composer scripts:

```bash
composer test:unit          # unit tests
composer test:integration   # integration tests
composer test:phpunit       # both suites
```

**Docker is the recommended way to run these commands.** It guarantees the correct PHP version and extensions without any local setup. Prefix any command above with `docker compose run --rm builder`:

```bash
docker compose run --rm builder composer test:unit
docker compose run --rm builder composer test:phpunit
```

The `builder` service mounts your working directory, so code changes are reflected immediately.

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

### Line length near the boundary

Several lines in `src/` sit just under the 120-character PSR-12 soft limit. Mechanical changes like renaming a class reference (e.g., `Exception` → `ConfigurationException`) can push a line past the limit and fail `test:lint`, even though the logic is unchanged. Always run `test:lint` after rename-style refactors.

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

**`IntegrationTestCase`** (`tests/Support/IntegrationTestCase.php`) — Base class for integration tests. Provides:

- `copyFixtures()` — Copies the test's `composer.json` (and any other `.json` files) to an isolated temp directory
- `runComposerInstall()` — Runs `composer update` in the temp directory
- `runMozart()` — Executes `Commands\Compose` against the temp directory with mocked Symfony I/O
- Automatic setup/teardown: creates a unique temp directory per test, cleans it up after

**`AstProcessingTestTrait`** (`tests/Support/AstProcessingTestTrait.php`) — Helpers for AST visitor unit tests:

- `processCodeWithVisitor($code, $visitor)` — Parses PHP code, applies a visitor via AST traversal, returns the printed result
- `wrapCode($code)` — Wraps code in `<?php` tags for the parser
- `extractCode($code)` — Removes PHP tags from pretty-printed output

### Test naming conventions

Test methods use the pattern `it_<action>_<expected_result>`:

```php
public function it_replaces_namespace_declarations(): void
public function it_does_not_double_prefix(): void
public function it_handles_nullable_type_hints(): void
```

Tests use the `#[Test]` PHPUnit attribute.

### Regression tests

Several GitHub issues have dedicated regression tests to prevent recurrence:

| Issue | Test location | What it prevents |
|---|---|---|
| #75 | `NamespaceReplacerTest` | `use ... as` alias handling |
| #81 | `ClassmapReplacerTest` | Class declaration replacement |
| #89 | `Integration/MoverFileOnce` | Duplicate "file already exists" errors |
| #93 | `ClassmapReplacerTest` | Namespace-aware class replacement |
| #159 | `Integration/Psr4ArrayAutoload` | PSR-4 arrays with non-existent directories |
| #177 | `Unit/Replace/Issues/Issue177Test` + `Integration/PhpDiPackage` | Nullable type hint double-prefixing |
| #209 | `FilesHandlerTest` | Restrictive file permissions (0700/0600) from Flysystem's default private visibility |
