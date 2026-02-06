# Architecture

Mozart is a Composer dependency bundler for WordPress plugins. It copies PHP dependencies into the plugin, then rewrites their namespaces and class names to avoid conflicts with other plugins shipping the same libraries.

## Execution flow

The `Commands\Compose::execute()` method drives the entire flow:

```
1. PackageFinder  - Resolve dependency tree from composer.json
2. Mover          - Copy package files to dep_directory / classmap_directory
3. Replacer       - Rewrite namespaces and class names in copied files
4. Mover          - Delete original vendor directories (if configured)
```

In code (`Commands\Compose`):

```php
$mover->deleteTargetDirs($packages);
$mover->movePackages($packages);
$replacer->replacePackages($packages);
$replacer->replaceParentInTree($packages);
$replacer->replaceParentClassesInDirectory($config->getClassmapDirectory());
$mover->deletePackageVendorDirectories();
```

The `replaceParentInTree` step is important: after replacing namespaces/classes within each package, it also updates references in parent packages that depend on them. This ensures package A (which requires package B) gets updated with the new names from package B.

## Configuration

Mozart config lives in the consuming project's `composer.json` under `extra.mozart`:

| Key | Required | Description |
|---|---|---|
| `dep_namespace` | Yes | Prefix added to namespaces (e.g., `MyPlugin\Dependencies`) |
| `dep_directory` | Yes | Where namespaced files are copied to (e.g., `lib/`) |
| `classmap_prefix` | Yes | Prefix added to global class names (e.g., `MP_`) |
| `classmap_directory` | Yes | Where classmap files are copied to (e.g., `classes/`) |
| `packages` | No | Limit which packages are processed. If empty, all `require` dependencies are processed. |
| `excluded_packages` | No | Packages to skip entirely during replacement |
| `override_autoload` | No | Override the autoloader configuration for specific packages |
| `delete_vendor_directories` | No | Whether to delete originals after moving (default: `true`) |

The config is read via `Config\ReadsConfig` and mapped through `netresearch/jsonmapper`.

## Dependency resolution

`PackageFinder::findPackages()` uses BFS (breadth-first search) with a visited set to flatten the dependency tree into a deduplicated list:

```php
$visited = [];
$queue = $packages;

while (!empty($queue)) {
    $package = array_shift($queue);
    if (isset($visited[$package->getName()])) continue;
    $visited[$name] = $package;
    // enqueue unvisited dependencies
}
```

Key behaviors:

- **Diamond dependencies** are handled by the `$visited` map: if A depends on B and C, and both depend on D, D is only processed once.
- **Circular dependencies** are broken by the same visited check.
- **Non-package requirements** (like `php` or `ext-json`) are filtered out by checking for `/` in the slug.

Dependencies are loaded recursively: `Package::loadDependencies()` calls back into `PackageFinder::getPackageBySlug()` for each requirement.

## Project structure

```
src/
  Commands/Compose.php              # Main execution flow (orchestrates everything)
  Console/Commands/Compose.php      # Symfony Console wrapper (memory error handler)
  Config/                           # Configuration models (Mozart, Package, Psr4, Classmap, Files, etc.)
  Composer/Autoload/                # Autoloader abstractions (NamespaceAutoloader, AbstractAutoloader)
  Replace/
    BaseReplacer.php                # Abstract base (holds autoloader reference)
    Replacer.php                    # Interface: replace(string): string
    StringReplacer.php              # Interface: same as Replacer but for class-map style
    Classmap/                       # ClassmapReplacer, DeclarationVisitor, NameReplacer, NameVisitor
    Namespace/                      # NamespaceReplacer, PrefixVisitor
    Support/                        # AstUtils, ExistenceCheckTrait, NameNodeContextTrait
  Mover.php                         # Copies files from vendor/ to target directories
  Replacer.php                      # Orchestrator (routes packages to correct replacer)
  PackageFinder.php                 # Dependency tree resolution (BFS with deduplication)
  PackageFactory.php                # Creates Package objects from composer.json
  FilesHandler.php                  # File I/O via Flysystem (read, write, copy, delete)
  Exceptions/                       # ConfigurationException, FileOperationException, MozartException

tests/
  Unit/                             # Mirrors src/ structure
  Integration/                      # Each test has its own directory with composer.json fixture
  Support/                          # IntegrationTestCase base class, AstProcessingTestTrait
```

## Key dependencies

| Package | Purpose |
|---|---|
| `nikic/php-parser` | AST-based PHP code transformation (supports v4 and v5) |
| `league/flysystem` | File system abstraction for all file I/O |
| `symfony/console` | CLI application framework |
| `symfony/finder` | File discovery (finding PHP files in directories) |
| `netresearch/jsonmapper` | Mapping JSON configuration to PHP objects |
