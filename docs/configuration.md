# Configuration

Mozart configuration lives in the `extra` property of your `composer.json` file. All core settings have smart defaults, so getting started requires very little setup.

## Minimal configuration

If your project has a PSR-4 autoload entry, an empty Mozart block is enough:

```json
{
    "name": "my-vendor/my-plugin",
    "autoload": {
        "psr-4": {
            "MyVendor\\MyPlugin\\": "src/"
        }
    },
    "extra": {
        "mozart": {}
    }
}
```

Mozart resolves this to:

| Setting | Resolved value | Source |
|---|---|---|
| `dep_namespace` | `MyVendor\MyPlugin\Dependencies` | Inferred from PSR-4 namespace |
| `dep_directory` | `vendor-prefixed/` | Static default |
| `classmap_directory` | `vendor-prefixed/` | Same as `dep_directory` |
| `classmap_prefix` | `MyVendor_MyPlugin_Dependencies_` | Derived from `dep_namespace` |
| `generate_autoloader` | `true` | Default |
| `delete_vendor_directories` | `true` | Default |

No PSR-4 entry? Mozart falls back to the package `name` field. For example, `my-vendor/my-plugin` becomes the namespace `MyVendor\MyPlugin\Dependencies`.

## How defaults work

Defaults are applied in a specific order, because some depend on others:

1. **`dep_directory`** — if empty, set to `vendor-prefixed/`. This is the most common convention in the WordPress ecosystem.
2. **`classmap_directory`** — if empty, set to the same value as `dep_directory`. Classmap files go into `classmap_directory/{package_name}/` subdirectories, while PSR-4 files go into `dep_directory/{namespace_path}/`, so there are no conflicts even when they share the same base directory.
3. **`dep_namespace`** — if empty, inferred using one of two strategies:
   - **PSR-4 autoload** (preferred): uses the first PSR-4 namespace from your `composer.json` autoload section and appends `\Dependencies`. For example, `MyPlugin\` becomes `MyPlugin\Dependencies`.
   - **Package name** (fallback): converts the `name` field from `composer.json` to a namespace. Each part is converted from kebab-case to PascalCase and joined with `\`, then `\Dependencies` is appended. For example, `coen-jacobs/my-plugin` becomes `CoenJacobs\MyPlugin\Dependencies`.
4. **`classmap_prefix`** — if empty, derived from `dep_namespace` by replacing `\` with `_` and appending `_`. For example, `MyPlugin\Dependencies` becomes `MyPlugin_Dependencies_`.

Any value you set explicitly in `composer.json` is never overwritten by defaults. You can set some values and let Mozart infer the rest.

## Verifying your configuration

Use the `mozart config` command to see the resolved configuration without running any file operations:

```
$ mozart config

Mozart Configuration (resolved from composer.json)

  dep_namespace:          MyPlugin\Dependencies      (inferred from PSR-4: MyPlugin\)
  dep_directory:          vendor-prefixed/            (default)
  classmap_directory:     vendor-prefixed/            (default, same as dep_directory)
  classmap_prefix:        MyPlugin_Dependencies_      (derived from dep_namespace)
  constant_prefix:        (not set)
  functions_prefix:       (not set)
  generate_autoloader:    true                        (default)
  delete_vendor_dirs:     true                        (default)
  packages:               (all require dependencies)
  excluded_packages:      (none)
```

Each value is annotated with its source: `(explicit)` for values set in `composer.json`, `(default)` for static defaults, `(inferred from PSR-4: ...)` or `(inferred from package name: ...)` for derived values, and `(derived from dep_namespace)` for computed values.

## Full example

For full control, you can set every option explicitly:

```json
"extra": {
    "mozart": {
        "dep_namespace": "CoenJacobs\\TestProject\\Dependencies\\",
        "dep_directory": "/src/Dependencies/",
        "classmap_directory": "/classes/dependencies/",
        "classmap_prefix": "CJTP_",
        "constant_prefix": "CJTP_",
        "functions_prefix": "cjtp_",
        "generate_autoloader": true,
        "packages": [
            "pimple/pimple"
        ],
        "excluded_packages": [
            "psr/container"
        ],
        "override_autoload": {
            "google/apiclient": {
                "classmap": [
                    "src/"
                ]
            }
        },
        "delete_vendor_directories": true
    }
},
```

## Core options

These are the primary settings that control how Mozart transforms your dependencies. All have defaults or can be inferred automatically.

- **`dep_namespace`** — the root namespace that each package will be put in. For example, if a package uses the `Pimple` namespace and your `dep_namespace` is `CoenJacobs\TestProject\Dependencies`, the package will be placed inside `CoenJacobs\TestProject\Dependencies\Pimple`. _Default: inferred from PSR-4 autoload or package name (see [How defaults work](#how-defaults-work))._
- **`dep_directory`** — the directory where namespaced package files are copied to. This should correspond to the namespace used in your autoloader. Best results are achieved when your projects use the [PSR-4 autoloader specification](http://www.php-fig.org/psr/psr-4/). _Default: `vendor-prefixed/`._
- **`classmap_directory`** — the directory where classmap-autoloaded files are stored. This directory needs to be autoloaded by a classmap in your project's autoloader. _Default: same as `dep_directory`._
- **`classmap_prefix`** — the prefix applied to all classes inside the classmap of bundled packages. For example, a class named `Pimple` with prefix `CJTP_` becomes `CJTP_Pimple`. _Default: derived from `dep_namespace` with `\` replaced by `_`._

**Important:** Mozart automatically processes the full dependency tree of the packages you specify. A package deep in the tree might use a classmap autoloader even if all your direct dependencies use PSR-4. The defaults handle this correctly (both directory settings are always populated), but for larger projects, setting values explicitly gives you more predictable results and clearer project structure.

## Optional options

- **`constant_prefix`** — prefix applied to global-scope constant declarations (`const` statements and `define()` calls). For example, with a prefix of `CJTP_`, a constant `MY_VERSION` becomes `CJTP_MY_VERSION`. PHP built-in constants are never prefixed. _Default: empty (disabled)._
- **`functions_prefix`** — prefix applied to global-scope function declarations. For example, with a prefix of `cjtp_`, a function `my_helper()` becomes `cjtp_my_helper()`. PHP built-in functions are never prefixed. _Default: empty (disabled)._
- **`generate_autoloader`** — generate a Composer-compatible autoloader inside `dep_directory` for all prefixed dependencies. When enabled, Mozart produces an `autoload.php` entry point and a `composer/` directory with PSR-4, classmap, and files autoloader support. Include it with `require_once __DIR__ . '/dep_directory/autoload.php';`. This replaces the need to manually configure autoloading in your project's `composer.json`. _Default: `true`._
- **`delete_vendor_directories`** — whether to delete the packages' vendor directories after processing. _Default: `true`._
- **`packages`** — array of package slugs to process (e.g., `["pimple/pimple"]`). Mozart automatically processes dependencies of these packages too. If absent or empty, all packages listed under `require` in your `composer.json` are included.
- **`excluded_packages`** — array of package slugs to skip during processing. Useful when a dependency defines sub-packages whose namespaces should remain unchanged.
- **`override_autoload`** — dictionary keyed by package name, with autoload settings to replace those in the original package's `composer.json` `autoload` property.

## After running Mozart

It is recommended to dump the autoloader after Mozart has finished running, in case there are new classes or namespaces generated that aren't included in the autoloader yet. See [usage.md](usage.md) for how to automate this with Composer scripts and how to configure your project's autoloader.
