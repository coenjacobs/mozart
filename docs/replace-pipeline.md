# Replace Pipeline

All code transformation in Mozart uses AST-based processing via `nikic/php-parser`. This replaced an earlier regex-based approach that couldn't handle complex PHP syntax correctly (e.g., nullable type hints like `?ClassName`).

## Overview

The `Replacer` class (in `src/Replace/Replacer.php`) orchestrates all replacement. It routes each package to the right replacer based on its autoloader type:

```
Replacer (orchestrator)
  -> getReplacerByAutoloader()
     -> NamespaceReplacer    (for PSR-4 / PSR-0 autoloaders)
     -> ClassmapReplacer     (for classmap autoloaders)
  -> Each replacer: parse PHP -> traverse AST with visitor -> print modified AST
```

For files autoloader entries, the `Replacer` inspects each file individually: namespaced files use `NamespaceReplacer`, global-scope files use `ClassmapReplacer`.

## Namespace replacement

**Location:** `src/Replace/Namespace/`

Used for packages with PSR-4 or PSR-0 autoloading. Adds a prefix to all namespace references.

### NamespaceReplacer

Entry point. Parses PHP code into an AST, creates a `PrefixVisitor` with the configured prefix and target namespace, traverses, and prints the result.

### PrefixVisitor

The core AST visitor for namespace prefixing. Handles:

- **Namespace declarations** (`namespace Foo\Bar;` -> `namespace Prefix\Foo\Bar;`)
- **Use statements** (`use Foo\Bar;` -> `use Prefix\Foo\Bar;`). Also tracks aliases to avoid double-prefixing references that resolve through a use statement.
- **Fully-qualified names** (`\Foo\Bar\Baz` -> `\Prefix\Foo\Bar\Baz`)
- **Relative names** resolved through aliases or the current namespace context
- **String arguments in existence-check functions** (see [Existence Check Handling](#existence-check-handling))

The visitor maintains state during traversal:
- `$currentNamespace` — tracks which namespace block we're inside
- `$aliases` — maps local alias names to their original FQNs from use statements

An unqualified name inside a namespace that's being prefixed is skipped, because it will resolve correctly through the already-prefixed namespace declaration.

## Classmap replacement

**Location:** `src/Replace/Classmap/`

Used for packages with classmap autoloading. Prefixes global-scope class, interface, trait, and enum names.

### Two-pass process

Classmap replacement requires two passes, unlike namespace replacement which only needs one:

**Pass 1 — Declaration renaming** (`ClassmapReplacer` + `DeclarationVisitor`):
- Traverses each file and renames class/interface/trait/enum declarations in the global namespace
- Records every rename in a `replacedClasses` map (`original => prefixed`)
- Skips declarations inside namespace blocks (those are handled by namespace replacement)

**Pass 2 — Reference updating** (`NameReplacer` + `NameVisitor`):
- Runs after all declarations have been renamed
- Uses the `replacedClasses` map to update references everywhere
- Called via `ParentReplacer::replaceParentClassesInDirectory()`
- Only replaces simple (non-namespaced) names that appear in the map
- `NameReplacer` implements `StringReplacer` (not `AutoloadReplacer`), so it has no `setAutoloader()` — it operates purely on the class map, independent of any autoloader context. The `StringReplacer` interface exists because pass-2 reference replacement is a simple string-map operation: look up a name in the collected renames and substitute it. It doesn't need the autoloader context that `AutoloadReplacer` provides, so using a separate interface keeps that dependency out

This two-pass design exists because you can't know the full set of renamed classes until all declarations have been processed.

### DeclarationVisitor

- Only operates on global namespace (enters namespace blocks with `DONT_TRAVERSE_CHILDREN`)
- Renames `class`, `interface`, `trait`, and `enum` declarations
- Uses `createSimpleTraverser()` (no `ParentConnectingVisitor`) to avoid stack overflow on large files

### NameVisitor

- Replaces `Name` nodes that match entries in the class map
- Only processes simple names (no namespace separator)
- Handles string arguments in existence-check functions
- Uses `createTraverser()` (with `ParentConnectingVisitor`) because it needs `NameNodeContextTrait`

## Shared support

**Location:** `src/Replace/Support/`

### AstUtils

Utility class shared by all replacers. Provides:

- `parseCode(string): ?array` — Parses PHP into AST. Caches the parser instance for efficiency.
- `printCode(array): string` — Prints AST back to PHP code.
- `createTraverser(...$visitors): NodeTraverser` — Creates traverser with `ParentConnectingVisitor` (needed when visitors use `NameNodeContextTrait` to check parent node types).
- `createSimpleTraverser(...$visitors): NodeTraverser` — Creates traverser without `ParentConnectingVisitor`. Use this for visitors that don't need parent access, to avoid stack overflow on deeply nested code.
- `getLastError(): ?string` — Returns the last parse error message.

### NameNodeContextTrait

Detects whether a `Name` node is part of a namespace or use statement. These statements are handled separately from general class references, so visitors need to skip them during normal name processing.

Requires `ParentConnectingVisitor` in the traverser (use `AstUtils::createTraverser()`).

### ExistenceCheckTrait

Handles string argument prefixing in PHP functions that accept fully-qualified names as strings:

| Function | Argument index | Notes |
|---|---|---|
| `function_exists()` | 0 | |
| `class_exists()` | 0 | |
| `interface_exists()` | 0 | |
| `trait_exists()` | 0 | |
| `enum_exists()` | 0 | |
| `constant()` | 0 | Supports `::` syntax and concatenation |
| `defined()` | 0 | Supports `::` syntax |
| `method_exists()` | 0 | |
| `property_exists()` | 0 | |
| `is_callable()` | 0 | Supports `::` syntax |
| `is_a()` | 1 | Second argument is the class name |
| `is_subclass_of()` | 1 | Second argument is the class name |
| `class_alias()` | 0, 1 | Both arguments are processed |

Special handling for `constant()`, `defined()`, and `is_callable()`: these accept `Class::member` syntax, so the trait splits on `::` and only prefixes the class/namespace portion.

Also handles concatenation patterns like `constant('Namespace\Class::' . $var)` where the left side of a `.` concatenation ends with `::`.

## Built-in symbol protection

Polyfill packages (e.g., `symfony/polyfill-php80`) define classes, interfaces, and enums that mirror PHP built-ins (`ValueError`, `Stringable`, etc.). Without protection, `DeclarationVisitor` would prefix these declarations, breaking the polyfill's purpose.

Mozart ships a generated database of PHP built-in symbols (`src/PhpSymbols/data/php-symbols.php`) covering PHP 7.4–8.5. The `BuiltInSymbols` class loads this file once and provides O(1) lookups via `isset()`.

The guard sits in `DeclarationVisitor::leaveNode()`: after extracting the original name from a class/interface/trait/enum declaration, it checks `isBuiltInType()` and returns early if the name matches a built-in. This prevents the symbol from entering the `replacedClasses` map, so Pass 2 (`NameVisitor`) naturally skips it too — no changes needed there.

The database is regenerated with `bash tools/generate-php-symbols.sh`, which runs each PHP version via Docker and merges the results. The interface also exposes `isBuiltInFunction()` and `isBuiltInConstant()` for future function and constant prefixing support.

## Directory guards

Multiple points in the replacement flow need `is_dir()` checks because directories may not exist:

- `Replacer::replacePackageByAutoloader()` — classmap source path may not exist
- `Replacer::replaceInDirectory()` — namespace directory may not exist
- `ParentReplacer::replaceParentClassesInDirectory()` — directory may not exist yet
- `NamespaceAutoloader::getFiles()` — PSR-4 paths (especially when defined as arrays) may list non-existent directories
- `Classmap::getFiles()` — classmap paths may not exist
- `FilesHandler::getFilesFromPath()` — uses Symfony Finder with `->exclude('vendor')` to avoid processing nested vendor directories
