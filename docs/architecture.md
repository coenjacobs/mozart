# Architecture

Mozart is a Composer dependency bundler for WordPress plugins. It copies PHP dependencies into the plugin, then rewrites their namespaces and class names to avoid conflicts with other plugins shipping the same libraries.

## Entry points

Mozart can run as a PHAR, a global Composer package, or a project dependency. All paths converge on the same execution chain:

```
bin/mozart  →  Console\Application  →  Console\Commands\Compose  →  Commands\Compose
```

`bin/mozart` detects whether it's running inside a PHAR or from source, and resolves the Composer autoloader accordingly (trying CWD vendor, global install paths, then local paths). `Console\Commands\Compose` registers a shutdown handler that catches memory exhaustion errors and prints a helpful message pointing to `docs/memory.md`, then delegates to `Commands\Compose::execute()`.

## Execution flow

`Commands\Compose::execute()` drives the entire flow:

```
1. Load config     - Read composer.json, extract extra.mozart settings
2. Find packages   - Resolve which packages to process (filtered or all)
3. Resolve tree    - Flatten dependency tree via PackageFinder (BFS)
4. Move files      - Copy package files to dep_directory / classmap_directory
5. Replace         - Rewrite namespaces and class names in copied files
6. Cross-replace   - Update parent packages with child package renames
7. Cleanup         - Delete original vendor directories (if configured)
```

**Package filtering** happens in step 2. If the `packages` config lists specific slugs, only those (plus their dependencies) are resolved. If `packages` is empty, all `require` dependencies from the root `composer.json` are loaded.

In code:

```php
$mover->deleteTargetDirs($packages);
$mover->movePackages($packages);
$replacer->replacePackages($packages);

$parentReplacer = new ParentReplacer($config, $replacer);
$parentReplacer->setReplacedClasses($replacer->getReplacedClasses());
$parentReplacer->replaceParentInTree($packages);
$parentReplacer->replaceParentClassesInDirectory($config->getClassmapDirectory());

if ($config->getDeleteVendorDirectories()) {
    $mover->deletePackageVendorDirectories();
}
```

The `ParentReplacer` handles cross-replacement: after `Replacer` rewrites namespaces/classes within each package, `ParentReplacer` propagates those renames into parent packages. This ensures package A (which requires package B) gets updated with the new names from package B.

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

### Configuration loading chain

Config loading follows this path:

```
composer.json  →  PackageFactory::createPackage()
                    →  Package::loadFromFile()        (ReadsConfig trait)
                    →  JsonMapper::map()              (JSON → PHP objects)
                    →  Package.extra.mozart → Mozart config object
```

The `ReadsConfig` trait (used by both `Package` and `Mozart`) provides `loadFromFile()` → `loadFromString()` → `loadFromStdClass()`, all ultimately delegating to `netresearch/jsonmapper` for mapping JSON to typed PHP objects.

`PackageFactory` creates `Package` objects and **caches them by file path**. This means the same package won't be parsed twice when it appears in multiple dependency chains (diamond dependencies). It also applies `override_autoload` settings at creation time, replacing a package's autoload config before any processing.

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

## Mover

The `Mover` class handles all file operations: preparing target directories, copying files, and cleaning up vendor directories.

### Target directory preparation

`deleteTargetDirs()` creates the `dep_directory` and `classmap_directory`, then recursively deletes existing output directories for each package. The target directory is determined per autoloader type:

- **PSR-4 / PSR-0** → `dep_directory` + namespace path
- **Classmap** → `classmap_directory` + package directory name
- **Files** → skipped (files are handled individually and may overlap with PSR-4 directories)

### File movement

`movePackages()` iterates each package's autoloaders, discovers files via `Autoloader::getFiles()`, and copies them to their target paths. Two deduplication mechanisms prevent issues:

- **Package-level**: Each package directory name is tracked. A package is only moved once even if it appears in multiple dependency chains.
- **File-level**: Each file's real path is tracked. A file is only copied once even if multiple autoloaders reference it (fix for issue #89).

### Vendor cleanup

`deletePackageVendorDirectories()` removes processed package directories from `vendor/`. If this leaves the vendor subdirectory empty (e.g., `vendor/psr/` after removing `vendor/psr/container/`), the parent directory is also removed. Symlinked directories are skipped.

## Exceptions

Mozart uses a small exception hierarchy:

```
MozartException (base)
├── ConfigurationException  — invalid or missing Mozart config
└── FileOperationException  — file read/write failures (caught and skipped in replacer)
```

`ConfigurationException` is thrown early in `Commands\Compose::execute()` if the Mozart config block is missing. `FileOperationException` is thrown by `FilesHandler` on I/O failures and is caught in the replacer loops to skip unreadable files gracefully.

## Project structure

```
src/
  Commands/Compose.php              # Main execution flow (orchestrates everything)
  Console/Commands/Compose.php      # Symfony Console wrapper (memory error handler)
  Config/                           # Configuration models (Mozart, Package, Psr4, Classmap, Files, etc.)
  Composer/Autoload/                # Autoloader abstractions (NamespaceAutoloader, AbstractAutoloader)
  Replace/
    Replacer.php                    # Orchestrator (routes packages to correct replacer)
    ParentReplacer.php              # Cross-replacement: propagates renames into parent packages
    AbstractAutoloadReplacer.php    # Abstract base (holds autoloader reference)
    AutoloadReplacer.php            # Interface: extends StringReplacer with setAutoloader()
    StringReplacer.php              # Interface: same as Replacer but for class-map style
    Classmap/                       # ClassmapReplacer, DeclarationVisitor, NameReplacer, NameVisitor
    Namespace/                      # NamespaceReplacer, PrefixVisitor
    Support/                        # AstUtils, ExistenceCheckTrait, NameNodeContextTrait
  Mover.php                         # Copies files from vendor/ to target directories
  PackageFinder.php                 # Dependency tree resolution (BFS with deduplication)
  PackageFactory.php                # Creates Package objects from composer.json
  FilesHandler.php                  # File I/O via Flysystem (read, write, copy, delete)
  Exceptions/                       # ConfigurationException, FileOperationException, MozartException

tests/
  Unit/                             # Mirrors src/ structure
  Integration/                      # Each test has its own directory with composer.json fixture
  Support/                          # IntegrationTestCase base class, AstProcessingTestTrait
```

## Flysystem visibility default

`FilesHandler` creates its `LocalFilesystemAdapter` with `Visibility::PUBLIC`. Without this, Flysystem defaults to private visibility — directories get `0700` and files get `0600`, which breaks setups where the web server user differs from the Composer user. If the adapter construction is ever changed, the permission tests in `FilesHandlerTest` will catch the regression.

## Key dependencies

| Package | Purpose |
|---|---|
| `nikic/php-parser` | AST-based PHP code transformation (supports v4 and v5) |
| `league/flysystem` | File system abstraction for all file I/O |
| `symfony/console` | CLI application framework |
| `symfony/finder` | File discovery (finding PHP files in directories) |
| `netresearch/jsonmapper` | Mapping JSON configuration to PHP objects |

**Note:** `composer.json` has a `conflict` section that pins several Symfony packages below v7.0 to maintain PHP 8.1 compatibility. Check this section before attempting Symfony upgrades.
