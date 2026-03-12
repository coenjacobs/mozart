# Mozart [![Latest Stable Version](https://poser.pugx.org/coenjacobs/mozart/v/stable.svg)](https://packagist.org/packages/coenjacobs/mozart) [![License](https://poser.pugx.org/coenjacobs/mozart/license.svg)](https://packagist.org/packages/coenjacobs/mozart) [![Total Downloads](https://poser.pugx.org/coenjacobs/mozart/downloads)](//packagist.org/packages/coenjacobs/mozart) [![Docker Image Pulls](https://img.shields.io/docker/pulls/coenjacobs/mozart.svg)](https://hub.docker.com/r/coenjacobs/mozart)
Composes all dependencies as a package inside a WordPress plugin. Load packages through Composer and have them wrapped inside your own namespace. Gone are the days when plugins could load conflicting versions of the same package, resulting in hard to reproduce bugs.

This package requires PHP 8.2 or higher in order to run the tool. You can use the resulting files as a bundle, requiring any PHP version you like, even PHP 5.2.

## How it works

Mozart takes your Composer dependencies, copies them into your plugin, and rewrites their namespaces and class names so they can't conflict with other plugins loading the same packages.

For namespaced packages, Mozart prefixes the namespace and updates all references:

```diff
-namespace Pimple;
+namespace CoenJacobs\TestProject\Dependencies\Pimple;

-use Psr\Container\ContainerInterface;
+use CoenJacobs\TestProject\Dependencies\Psr\Container\ContainerInterface;

 class Container implements ContainerInterface
```

For packages using global-scope classes, Mozart adds a prefix to class names:

```diff
-class Container {
+class CJTP_Container {
     // ...
 }

-$container = new Container();
+$container = new CJTP_Container();
```

This happens across the full dependency tree — namespace declarations, `use` statements, type hints, string references in `class_exists()` calls, and more. The result is a self-contained copy of your dependencies that won't collide with any other plugin's versions.

## Installation

Mozart brings its own dependencies to the table and that potentially introduces its own problems (yes, I realise how meta that is, for a package like this). That's why installing Mozart in isolation, either through the Docker container, the available PHAR file or installing Mozart as a global dependency with Composer is preferred.

```
docker run --rm -it -v ${PWD}:/project/ coenjacobs/mozart /mozart/bin/mozart compose
```

See [docs/installation.md](docs/installation.md) for all installation methods (Docker, PHAR, Composer).

## Configuration

Mozart potentially requires zero configuration. When your project has a PSR-4 autoload entry or a package name in `composer.json`, Mozart infers everything it needs: the dependency namespace, target directories, classmap prefix, and the generated dependency autoloader. In the default setup, run `mozart compose` and then include the generated `dep_directory/autoload.php` from your plugin bootstrap.

If you want to customize the behavior, add an `extra.mozart` block to your `composer.json`. Even an empty block is valid — Mozart fills in every setting it can infer:

```json
"extra": {
    "mozart": {}
}
```

You can verify the resolved configuration with `mozart config`.

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

The core settings and their defaults:

- `dep_namespace` — root namespace for bundled packages. _Default: inferred from your PSR-4 autoload namespace + `\Dependencies`, or from the package name._
- `dep_directory` — target directory for namespaced package files. _Default: `vendor-prefixed/`._
- `classmap_directory` — target directory for classmap package files. _Default: same as `dep_directory`._
- `classmap_prefix` — prefix applied to classmap class names. _Default: derived from `dep_namespace` with `\` replaced by `_`._
- `constant_prefix` — prefix for global-scope constants. _Default: derived from `classmap_prefix` by uppercasing it._
- `functions_prefix` — prefix for global-scope functions. _Default: derived from `classmap_prefix` by lowercasing it._
- `generate_autoloader` — generate a Composer-compatible autoloader for prefixed dependencies. _Default: `true`._

See [docs/configuration.md](docs/configuration.md) for the full configuration reference with defaults and inference logic.

## Supported versions

| Version | Documentation | Latest release |
|---|---|---|
| 1.3 | [README.md](https://github.com/coenjacobs/mozart/blob/master/README.md) (in development) | — |
| 1.2 | [README.md](https://github.com/coenjacobs/mozart/blob/release-1.2/README.md) | [1.2.0](https://github.com/coenjacobs/mozart/releases/tag/1.2.0) |
| 1.1 | [README.md](https://github.com/coenjacobs/mozart/blob/release-1.1/README.md) | [1.1.4](https://github.com/coenjacobs/mozart/releases/tag/1.1.4) |

### No longer supported

| Version | Documentation | Latest release |
|---|---|---|
| 1.0 | [README.md](https://github.com/coenjacobs/mozart/blob/release-1.0/README.md) | [1.0.10](https://github.com/coenjacobs/mozart/releases/tag/1.0.10) |
| 0.7 | [README.md](https://github.com/coenjacobs/mozart/blob/release-0.7/README.md) | [0.7.1](https://github.com/coenjacobs/mozart/releases/tag/0.7.1) |
| 0.6 | [README.md](https://github.com/coenjacobs/mozart/blob/release-0.6/README.md) | [0.6.0](https://github.com/coenjacobs/mozart/releases/tag/0.6.0) |

## Further reading

| Document | Description |
|---|---|
| [docs/installation.md](docs/installation.md) | All installation methods: Docker, PHAR, Composer |
| [docs/configuration.md](docs/configuration.md) | Full configuration reference with defaults and inference |
| [docs/usage.md](docs/usage.md) | Automating Mozart with Composer scripts and using the generated autoloader |
| [docs/docker.md](docs/docker.md) | Docker registries, tag strategy, multi-architecture support |
| [docs/background.md](docs/background.md) | Why Mozart was created and how it compares to PHP-Scoper |
