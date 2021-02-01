# Mozart [![Latest Stable Version](https://poser.pugx.org/coenjacobs/mozart/v/stable.svg)](https://packagist.org/packages/coenjacobs/mozart) [![License](https://poser.pugx.org/coenjacobs/mozart/license.svg)](https://packagist.org/packages/coenjacobs/mozart) [![Docker Image Pulls](https://img.shields.io/docker/pulls/coenjacobs/mozart.svg)](https://hub.docker.com/r/coenjacobs/mozart)
Composes all dependencies as a package inside a WordPress plugin. Load packages through Composer and have them wrapped inside your own namespace. Gone are the days when plugins could load conflicting versions of the same package, resulting in hard to reproduce bugs.

This package requires PHP 7.3 or higher in order to run the tool. You can use the resulting files as a bundle, requiring any PHP version you like, even PHP 5.2.

**Warning:** This package is very experimental and breaking changes are very likely until version 1.0.0 is tagged. Use with caution, always wear a helmet when using this in production environments.

## Installation
Mozart brings its own dependencies to the table and that potentially introduces its own problems (yes, I realise how meta that is, for a package like this). That's why installing Mozart in isolation, either through the Docker container, the available PHAR file or installing Mozart as a global dependency with Composer is prefered. In all cases, the [configuration](#configuration) still needs to be placed in the `composer.json` file of the project iself.

### Docker
Pull the Docker image from the registry:

```
docker pull coenjacobs/mozart
```

Then you can start the container and run the `mozart compose` command in the container. In a single command:

```
docker run --rm -it -v ${PWD}:/project/ coenjacobs/mozart /mozart/bin/mozart compose
```

Above command automatically adds the current working directory as a volume into the designated directory for the project: `/project/`. In the Docker container, Mozart is installed in the `/mozart/` directory. Using the above command will run Mozart on the current working directory.

Please note that the Docker image for Mozart is only available starting from the `latest` build of version 0.7.0. The `latest` tag is always the latest build of the `master` branch and not a stable version. You can see [all available tags on Docker Hub](https://hub.docker.com/r/coenjacobs/mozart/tags).

### PHAR (via Phive)
Mozart can be installed via [Phive](https://github.com/phar-io/phive):

```
phive install coenjacobs/mozart --force-accept-unsigned
```

Alternatively, the `mozart.phar` file can be [downloaded from the releases page](https://github.com/coenjacobs/mozart/releases) and then be run from your project directory:

```
php mozart.phar compose
```

### Composer
To install Mozart and its dependencies, without conflicting with the dependencies of your project, it is recommended that you install Mozart as a global package, if you choose to install Mozart via Composer.

#### Global package
Using the `global` command when installing Mozart, it will be installed as a system wide package: 

```
composer global require coenjacobs/mozart
```

You can then find the bin file named `mozart` inside your `~/.composer/vendor/bin/` directory and run it from your project directory, referencing the full path to the bin file:

```
~/.composer/vendor/bin/mozart compose
```

#### Development dependency of your project
You can install through Composer in the project itself, only required in development environments:

```
composer require coenjacobs/mozart --dev
```

This gives you a bin file named `mozart` inside your `vendor/bin` directory, after loading the whole package inside your project. Try running `vendor/bin/mozart` to verify it works.

After configuring Mozart properly, the `mozart compose` command does all the magic:

```
vendor/bin/mozart compose
```

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
        ],
        "exclude_packages": [
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

**Important:** Since Mozart automatically processes the full dependency tree of the packages you specify, you **need to specify all these configuration options**, because you can't reliably determine what kind of autoloaders are being used in the full dependency tree. A package way down the tree might suddenly use a classmap autoloader for example. Make sure you also include the namespace directory and classmap directory in your own autoloader, so they are always loaded.

The following configuration is optional:

- `delete_vendor_directories` is a boolean flag to indicate if the packages' vendor directories should be deleted after being processed. _default: true_.
- `packages` is an optional array that defines the packages to be processed by Mozart. The array requires the slugs of packages in the same format as provided in your `composer.json`. Mozart will automatically rewrite dependencies of these packages as well. You don't need to add dependencies of these packages to the list. If this field is absent, all packages listed under composer require will be included.
- `exclude_packages` is an optional array that defines the packages to be excluded from the processing performed by Mozart. This is useful if some of the packages in the `packages` array define dependent packages whose namespaces you want to keep unchanged. The array requires the slugs of the packages, as in the case of the `packages` array.
- `override_autoload` a dictionary, keyed with the package names, of autoload settings to replace those in the original packages' `composer.json` `autoload` property.

After Composer has loaded the packages as defined in your `composer.json` file, you can now run `mozart compose` and Mozart will bundle your packages according to the above configuration. It is recommended to dump the autoloader after Mozart has finished running, in case there are new classes or namespaces generated that aren't included in the autoloader yet. 

## Scripts
Mozart is designed to install and be forgotten about. Using Composer scripts, the Mozart script can be run as soon as Composer either installs a new package, or updates an already installed one. This ensures that the packages you want to bundle, are always bundled in the latest installed version, automatically. These scripts also offer you the possibility to script dumping the autoloader, after Mozart is finished running:

```
"scripts": {
    "post-install-cmd": [
        "\"vendor/bin/mozart\" compose",
        "composer dump-autoload"
    ],
    "post-update-cmd": [
        "\"vendor/bin/mozart\" compose",
        "composer dump-autoload"
    ]
}
```

When using Mozart through its Docker container, you can replace the `"\"vendor/bin/mozart\" compose",` lines with the actual commands you use to [run the Docker container](#docker) for your specific project. Running Mozart from inside the Docker container is really fast and shouldn't take more than a couple seconds.

## Background and philosophy
Mozart is designed to bridge the gap between the WordPress ecosytem and the vast packages ecosystem of PHP as a whole. Since WordPress is such an end-user friendly focussed CMS (for good reasons), there is no place within the ecosystem where an end-user would be burdened with using a developers tool like Composer. Also, since WordPress has to run on basically any hosting infrastructure, running Composer to install packages from the administration panel (trust me, I've tried - it can be done) is a mission impossible to make it happen and compatible with every server out there.

But why make a new tool for this? There are other tools that enable you to do this, right? Yes, there are now. [PHP-Scoper](https://github.com/humbug/php-scoper), for example. PHP-Scoper is a fantastic tool, that does the job right. But, PHP-Scoper wasn't available back when I started the Mozart project. Also, PHP-Scoper has a few limitations (no support for classmap autoloaders, for example) that were and are still quite common within the WordPress ecosystem. Finally, PHP-Scoper can be quite the tool to add to your development flow, while Mozart was designed to be _simple to implement_, specifically tailored for WordPress projects.

The key values of what's important to Mozart:
- Must be able to be easily installable by a developer, preferably in a specific version.
- Distribution must be done through an existing package manager or easily maintained separately.
- Shouldn't add a whole layer of complexity to the development process, i.e. learning a whole new tool/language.

Mozart always has been and always will be geared towards solving the conflicting dependencies problem in the WordPress ecosystem, as efficiently and opinionated as possible. By being opinionated in certain ways and specifically focussed on WordPress projects, Mozart has quickly become easy to understand and implement.
