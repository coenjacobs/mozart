# Testing

## Test Structure

```
tests/
├── Unit/           # Fast, isolated tests (no external dependencies)
├── Integration/    # Full Mozart flow tests (runs composer + Mozart)
└── Support/        # Shared test utilities
```

**Unit tests** test individual components in isolation. Each integration test has its own directory with a `composer.json` fixture.

## Running Tests Locally

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

## Docker Services

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

## GitHub Actions

The CI workflow (`.github/workflows/main.yml`) runs:

- **Unit tests**: All PHP versions (8.1, 8.2, 8.3, 8.4, 8.5)
- **Integration tests**: PHP 8.1 + 8.5 only (lowest and highest supported)

The PHP version matrix works via the `PHP_VERSION` build arg passed to Docker.

## Adding Tests

- **Unit test**: Add to `tests/Unit/`, mirroring `src/` structure. Use namespace `CoenJacobs\Mozart\Tests\Unit\...`
- **Integration test**: Create directory in `tests/Integration/` with test file + `composer.json`. Use namespace `CoenJacobs\Mozart\Tests\Integration\...`
- After adding new test files, run `composer dump-autoload` to update the autoloader
