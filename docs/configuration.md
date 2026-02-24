# Configuration

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

## Required options

- `dep_namespace` defines the root namespace that each package will be put in. Example: Should the package we're loading be using the `Pimple` namespace, then the package will be put inside the `CoenJacobs\\TestProject\\Dependencies\\Pimple` namespace, when using the configuration example above.
- `dep_directory` defines the directory the files of the package will be stored in. Note that the directory needs to correspond to the namespace being used in your autoloader and the namespace defined for the bundled packages. Best results are achieved when your projects are using the [PSR-4 autoloader specification](http://www.php-fig.org/psr/psr-4/).
- `classmap_directory` defines the directory files that are being autoloaded through a classmap, will be stored in. Note that this directory needs to be autoloaded by a classmap in your projects autoloader.
- `classmap_prefix` defines the prefix that will be applied to all classes inside the classmap of the package you bundle. Say a class named `Pimple` and the defined prefix of `CJTP_` will result in the class name `CJTP_Pimple`.

**Important:** Since Mozart automatically processes the full dependency tree of the packages you specify, you **need to specify all these configuration options**, because you can't reliably determine what kind of autoloaders are being used in the full dependency tree. A package way down the tree might suddenly use a classmap autoloader for example. Make sure you also include the namespace directory and classmap directory in your own autoloader, so they are always loaded.

## Optional options

- `constant_prefix` defines the prefix applied to global-scope constant declarations (`const` statements and `define()` calls). For example, with a prefix of `CJTP_`, a constant `MY_VERSION` becomes `CJTP_MY_VERSION`. PHP built-in constants are never prefixed. _default: empty (disabled)_.
- `functions_prefix` defines the prefix applied to global-scope function declarations. For example, with a prefix of `cjtp_`, a function `my_helper()` becomes `cjtp_my_helper()`. PHP built-in functions are never prefixed. _default: empty (disabled)_.
- `delete_vendor_directories` is a boolean flag to indicate if the packages' vendor directories should be deleted after being processed. _default: true_.
- `packages` is an optional array that defines the packages to be processed by Mozart. The array requires the slugs of packages in the same format as provided in your `composer.json`. Mozart will automatically rewrite dependencies of these packages as well. You don't need to add dependencies of these packages to the list. If this field is absent, all packages listed under composer require will be included.
- `excluded_packages` is an optional array that defines the packages to be excluded from the processing performed by Mozart. This is useful if some of the packages in the `packages` array define dependent packages whose namespaces you want to keep unchanged. The array requires the slugs of the packages, as in the case of the `packages` array.
- `override_autoload` a dictionary, keyed with the package names, of autoload settings to replace those in the original packages' `composer.json` `autoload` property.

## After running Mozart

It is recommended to dump the autoloader after Mozart has finished running, in case there are new classes or namespaces generated that aren't included in the autoloader yet. See [usage.md](usage.md) for how to automate this with Composer scripts and how to configure your project's autoloader.
