# Mozart [![Build Status](https://api.travis-ci.org/coenjacobs/mozart.png)](https://travis-ci.org/coenjacobs/mozart) [![Latest Stable Version](https://poser.pugx.org/coenjacobs/mozart/v/stable.svg)](https://packagist.org/packages/coenjacobs/mozart) [![License](https://poser.pugx.org/coenjacobs/mozart/license.svg)](https://packagist.org/packages/coenjacobs/mozart)
Composes all dependencies as a package inside a WordPress plugin. Load packages through Composer and have them wrapped inside your own namespace. Gone are the days when plugins could load conflicting versions of the same package, resulting in hard to reproduce bugs.

This package requires PHP 5.5 or higher in order to run the tool. You can use the resulting files as a bundle, requiring any PHP version you like, even PHP 5.2. 

**Warning:** This package is very experimental and breaking changes are very likely until version 1.0.0 is tagged. Use with caution, always wear a helmet when using this in production environments.

## Installation
Install through Composer, only required in development environments:

`composer require coenjacobs/mozart --dev`

This gives you a bin file named `mozart` inside your `vendor/bin` directory, after loading the whole package inside your project. Try running `vendor/bin/mozart` to verify it works.

After configuring Mozart properly, the `mozart compose` command does all the magic.

## Configuration
Mozart requires little configuration. All you need to do is tell it where the bundled dependencies are going to be stored and what namespace they should be put inside. This configuration needs to be done in the `extra` property of your `composer.json` file:

```
"extra": {
    "mozart": {
        "dep_namespace": "CoenJacobs\\TestProject\\Dependencies\\",
        "dep_directory": "/src/Dependencies/",
        "classmap_directory": "/classes/dependencies/",
        "classmap_prefix": "CJTP_",
        "packages": [
            "pimple/pimple"
        ]
    }
},
```

The following configuration values are required:

- `dep_namespace` defines the root namespace that each package will be put in. Example: Should the package we're loading be using the `Pimple` namespace, then the package will be put inside the `CoenJacobs\\TestProject\\Dependencies\\Pimple` namespace, when using the configuration example above.
- `dep_directory` defines the directory the files of the package will be stored in. Note that the directory needs to correspond to the namespace being used in your autoloader and the namespace defined for the bundled packages. Best results are achieved when your projects are using the [PSR-4 autoloader specification](http://www.php-fig.org/psr/psr-4/).
- `classmap_directory` defines the directory files that are being autoloaded through a classmap, will be stored in. Note that this directory needs to be autoloaded by a classmap in your projects autoloader.
- `classmap_prefix` defines the prefix that will be applied to all classes inside the classmap of the package you bundle. Say a class named `Pimple` and the defined prefix of `CJTP_` will result in the class name `CJTP_Pimple`.
- `packages` is an array that defines the packages that need to be processed by Mozart. The array requires the slugs of packages in the same format as provided in your `composer.json`.

After Composer has loaded the packages as defined in your `composer.json` file, you can now run `mozart compose` and Mozart will bundle your packages according to the above configuration.

## Scripts
Mozart is designed to install and be forgotten about. Using Composer scripts, the Mozart script can be run as soon as Composer either installs a new package, or updates an already installed one. This ensures that the packages you want to bundle, are always bundled in the latest installed version, automatically:

```
"scripts": {
    "post-install-cmd": [
        "\"vendor/bin/mozart\" compose"
    ],
    "post-update-cmd": [
        "\"vendor/bin/mozart\" compose"
    ]
}
```