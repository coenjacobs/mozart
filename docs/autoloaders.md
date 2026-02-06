# Autoloader Types

Each Composer package can have multiple autoloader types defined in its `composer.json`. The autoloader type determines three things in Mozart:

1. **How files are discovered** for the package
2. **Where files are moved** to in the target project
3. **Which replacer** handles the code transformation

## Overview

| Type | Class | Moved to | Replacer | Base class |
|---|---|---|---|---|
| PSR-4 | `Config\Psr4` | `dep_directory` | `NamespaceReplacer` | `NamespaceAutoloader` |
| PSR-0 | `Config\Psr0` | `dep_directory` | `NamespaceReplacer` | `NamespaceAutoloader` |
| Classmap | `Config\Classmap` | `classmap_directory` | `ClassmapReplacer` | `AbstractAutoloader` |
| Files | `Config\Files` | Both (depends on file) | Either (depends on file) | `AbstractAutoloader` |

## Processing order

`Config\Autoload::setupAutoloaders()` processes autoloader types in a specific order: PSR-4 first, then PSR-0, then classmap, then **files last**. This order matters because the Files autoloader uses `isInsidePsrPath()` to skip files already covered by PSR-4 or PSR-0 paths. If files were processed first, this overlap detection wouldn't work.

## PSR-4 and PSR-0

Both extend `NamespaceAutoloader` and share the same file discovery and moving logic. The difference is in target path resolution:

- **PSR-4** overrides `getNamespacePath()` to convert the namespace to a directory path (e.g., `Foo\Bar` → `Foo/Bar/`). Files are placed under `dep_directory/Foo/Bar/`.
- **PSR-0** inherits the default `getNamespacePath()` which returns an empty string. Files go directly into `dep_directory/` without namespace-based subdirectories.

Both share `getSearchNamespace()` which returns the namespace trimmed of trailing backslashes.

**File discovery:** Iterates `paths` from the autoload config, resolves each to a directory under `vendor/{package}/`, and collects all files recursively.

**Key edge case — array paths:** PSR-4 autoload entries can be either a string or an array:

```json
{
    "autoload": {
        "psr-4": {
            "Namespace\\": "src/"
        }
    }
}
```

or:

```json
{
    "autoload": {
        "psr-4": {
            "Namespace\\": ["src/", "lib/"]
        }
    }
}
```

When paths is an array, some directories may not exist on disk. `NamespaceAutoloader::getFiles()` skips non-existent directories with an `is_dir()` check.

## Classmap

Handles packages that define classmap autoloading. The config can contain both directory paths and individual `.php` file paths:

```json
{
    "autoload": {
        "classmap": ["src/", "lib/helpers.php"]
    }
}
```

**File discovery:** Directories are scanned recursively for all files. Individual `.php` files are found by name within the package directory. Non-existent directories are skipped.

**Replacement:** Uses `ClassmapReplacer` which only renames class/interface/trait declarations in the global namespace. See [Replace Pipeline](replace-pipeline.md) for the two-pass process.

## Files

The most complex autoloader type. Handles Composer's `files` autoloading:

```json
{
    "autoload": {
        "files": ["src/functions.php", "src/helpers.php"]
    }
}
```

**Complexity:** Unlike the other types, the Files autoloader can't assume anything about the content of each file. Each file might be:

- A namespaced file (has a `namespace` declaration) — routed to `dep_directory`, processed with `NamespaceReplacer`
- A global-scope file (no namespace) — routed to `classmap_directory`, processed with `ClassmapReplacer`

**Namespace detection:** `Files::getDetectedNamespace()` parses each file's AST to find a `Namespace_` node. Results are cached in `$detectedNamespaces`.

**PSR overlap handling:** Files that are already inside a PSR-4 or PSR-0 path from the same package are skipped (`isInsidePsrPath()`). This prevents duplicate processing when a file is listed in both `files` and covered by a PSR-4 path.

**Target path determination:** `Files::getTargetFilePath()` routes based on detected namespace:
- Namespaced file → `dep_directory/{namespace path}/{filename}`
- Global file → `classmap_directory/{package name}/{filename}`

## Autoloader class hierarchy

```
Autoloader (interface)
  └── AbstractAutoloader (abstract)
        ├── NamespaceAutoloader (abstract)
        │     ├── Psr4
        │     └── Psr0
        ├── Classmap
        └── Files
```

All autoloaders implement:
- `processConfig($autoloadConfig)` — Parse the autoload entry from composer.json
- `getFiles(FilesHandler)` — Discover files to process
- `getTargetFilePath(SplFileInfo)` — Determine where a file should be moved to
- `getSearchNamespace()` — Return the namespace to search for (throws on Classmap/Files)
- `getOutputDir($basePath, $autoloadPath)` — Combines a base directory with an autoload-specific path (e.g., `dep_directory` + namespace path), converting backslashes to `DIRECTORY_SEPARATOR`

## Nested vendor directories

`FilesHandler::getFilesFromPath()` uses Symfony Finder with `->exclude('vendor')` to avoid descending into nested vendor directories. Without this, a package that contains its own `vendor/` directory (e.g., from a committed lock file) would have those nested dependencies incorrectly processed.
