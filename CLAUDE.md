# Mozart

Composer dependency bundler for WordPress plugins. Copies dependencies into the plugin and rewrites namespaces/class names to avoid conflicts.

## Quick reference

```bash
docker compose run --rm builder composer test:unit          # unit tests
docker compose run --rm builder composer test:integration   # integration tests
docker compose run --rm builder composer test:phpunit       # both suites
docker compose run --rm builder composer dump-autoload      # after adding files
```

## Documentation

All project documentation lives in `docs/`. Read the relevant file before working in that area.

| File | Contents |
|---|---|
| [docs/architecture.md](docs/architecture.md) | Entry points, execution flow, Mover, configuration loading chain, dependency resolution, exceptions, project structure |
| [docs/replace-pipeline.md](docs/replace-pipeline.md) | AST-based replacement system: namespace and classmap replacers, visitors, support traits, directory guards |
| [docs/autoloaders.md](docs/autoloaders.md) | PSR-4, PSR-0, classmap, and files autoloader types: processing order, file discovery, movement, replacer routing |
| [docs/testing.md](docs/testing.md) | Running tests, Docker services, CI checks, test utilities (IntegrationTestCase, AstProcessingTestTrait), regression tests |
| [docs/memory.md](docs/memory.md) | Memory requirements for AST processing, how to increase limits |
| [docs/docker.md](docs/docker.md) | Docker registries, tag strategy, Dockerfile stages, Docker Compose services, PHP configuration |
| [docs/usage.md](docs/usage.md) | Automating Mozart with Composer scripts, configuring the project autoloader |
| [docs/background.md](docs/background.md) | Why Mozart exists, comparison with PHP-Scoper, project values |

## Key things to know

- **All public methods in `src/` require a docblock** or the `test:docs` CI check fails.
- **After adding new files**, run `composer dump-autoload` before testing.
- **Classmap replacement is two-pass**: declarations are renamed first, then references are updated using the collected rename map. See [docs/replace-pipeline.md](docs/replace-pipeline.md).
- **Files autoloader** is the most complex autoloader type — each file is inspected individually to determine if it's namespaced or global-scope. See [docs/autoloaders.md](docs/autoloaders.md).
- **Directory guards (`is_dir()`)** are required before any file discovery operation. Multiple locations in `Replacer`, autoloaders, and `FilesHandler` depend on these. See [docs/replace-pipeline.md](docs/replace-pipeline.md).
- **After resolving a non-trivial bug or discovering non-obvious behavior**, run `/learned` to capture the lesson in the right doc file before moving on.
