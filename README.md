# Mozart [![Latest Stable Version](https://poser.pugx.org/coenjacobs/mozart/v/stable.svg)](https://packagist.org/packages/coenjacobs/mozart) [![License](https://poser.pugx.org/coenjacobs/mozart/license.svg)](https://packagist.org/packages/coenjacobs/mozart) [![Total Downloads](https://poser.pugx.org/coenjacobs/mozart/downloads)](//packagist.org/packages/coenjacobs/mozart) [![Docker Image Pulls](https://img.shields.io/docker/pulls/coenjacobs/mozart.svg)](https://hub.docker.com/r/coenjacobs/mozart)
Composes all dependencies as a package inside a WordPress plugin. Load packages through Composer and have them wrapped inside your own namespace. Gone are the days when plugins could load conflicting versions of the same package, resulting in hard to reproduce bugs.

This package requires PHP 8.1 or higher in order to run the tool. You can use the resulting files as a bundle, requiring any PHP version you like, even PHP 5.2.

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

Mozart brings its own dependencies to the table and that potentially introduces its own problems (yes, I realise how meta that is, for a package like this). That's why installing Mozart in isolation, either through the Docker container, the available PHAR file or installing Mozart as a global dependency with Composer is preferred. In all cases, the [configuration](#configuration) still needs to be placed in the `composer.json` file of the project itself.

```
docker run --rm -it -v ${PWD}:/project/ coenjacobs/mozart /mozart/bin/mozart compose
```

See [docs/installation.md](docs/installation.md) for all installation methods (Docker, PHAR, Composer).

## Configuration
Mozart requires little configuration. All you need to do is tell it where the bundled dependencies are going to be stored and what namespace they should be put inside. This configuration needs to be done in the `extra` property of your `composer.json` file:

```json
"extra": {
    "mozart": {
        "dep_namespace": "CoenJacobs\\TestProject\\Dependencies\\",
        "dep_directory": "/src/Dependencies/",
        "classmap_directory": "/classes/dependencies/",
        "classmap_prefix": "CJTP_",
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

The following configuration values are required:

- `dep_namespace` defines the root namespace that each package will be put in. Example: Should the package we're loading be using the `Pimple` namespace, then the package will be put inside the `CoenJacobs\\TestProject\\Dependencies\\Pimple` namespace, when using the configuration example above.
- `dep_directory` defines the directory the files of the package will be stored in. Note that the directory needs to correspond to the namespace being used in your autoloader and the namespace defined for the bundled packages. Best results are achieved when your projects are using the [PSR-4 autoloader specification](http://www.php-fig.org/psr/psr-4/).
- `classmap_directory` defines the directory files that are being autoloaded through a classmap, will be stored in. Note that this directory needs to be autoloaded by a classmap in your projects autoloader.
- `classmap_prefix` defines the prefix that will be applied to all classes inside the classmap of the package you bundle. Say a class named `Pimple` and the defined prefix of `CJTP_` will result in the class name `CJTP_Pimple`.

See [docs/configuration.md](docs/configuration.md) for optional configuration, the full dependency tree caveat, and autoloader setup.

## Further reading

| Document | Description |
|---|---|
| [docs/installation.md](docs/installation.md) | All installation methods: Docker, PHAR, Composer |
| [docs/configuration.md](docs/configuration.md) | Full configuration reference: required and optional options |
| [docs/usage.md](docs/usage.md) | Automating Mozart with Composer scripts, configuring your project's autoloader |
| [docs/docker.md](docs/docker.md) | Docker registries, tag strategy, multi-architecture support |
| [docs/background.md](docs/background.md) | Why Mozart was created and how it compares to PHP-Scoper |
