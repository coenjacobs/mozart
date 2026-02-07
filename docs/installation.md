# Installation

Mozart brings its own dependencies to the table and that potentially introduces its own problems (yes, I realise how meta that is, for a package like this). That's why installing Mozart in isolation, either through the Docker container, the available PHAR file or installing Mozart as a global dependency with Composer is preferred. In all cases, the [configuration](configuration.md) still needs to be placed in the `composer.json` file of the project itself.

## Docker (recommended)

Run Mozart on your current working directory:

```
docker run --rm -it -v ${PWD}:/project/ coenjacobs/mozart /mozart/bin/mozart compose
```

Images are available from both Docker Hub (`coenjacobs/mozart`) and GitHub Container Registry (`ghcr.io/coenjacobs/mozart`), with multi-architecture support (amd64, arm64, arm/v7). See [docker.md](docker.md) for registries, tag strategy, and all available options.

## PHAR (via Phive)

Mozart can be installed via [Phive](https://github.com/phar-io/phive):

```
phive install coenjacobs/mozart --force-accept-unsigned
```

Alternatively, the `mozart.phar` file can be [downloaded from the releases page](https://github.com/coenjacobs/mozart/releases) and then be run from your project directory:

```
php mozart.phar compose
```

## Composer

To install Mozart and its dependencies, without conflicting with the dependencies of your project, it is recommended that you install Mozart as a global package, if you choose to install Mozart via Composer.

### Global package

Using the `global` command when installing Mozart, it will be installed as a system wide package:

```
composer global require coenjacobs/mozart
```

You can then find the bin file named `mozart` inside your `~/.composer/vendor/bin/` directory and run it from your project directory, referencing the full path to the bin file:

```
~/.composer/vendor/bin/mozart compose
```

### Development dependency of your project

You can install through Composer in the project itself, only required in development environments:

```
composer require coenjacobs/mozart --dev
```

This gives you a bin file named `mozart` inside your `vendor/bin` directory, after loading the whole package inside your project. Try running `vendor/bin/mozart` to verify it works.

After configuring Mozart properly, the `mozart compose` command does all the magic:

```
vendor/bin/mozart compose
```

## See also

- [configuration.md](configuration.md) — Required and optional configuration options
