[![PHPUnit ](.github/coverage.svg)](https://brianhenryie.github.io/strauss/)

# Strauss

A tool to prefix namespaces and classnames in PHP files to avoid autoloading collisions.

A fork of [Mozart](https://github.com/coenjacobs/mozart/). For [Composer](https://getcomposer.org/) for PHP.

The primary use case is WordPress plugins, where different plugins active in a single WordPress install could each include different versions of the same library. The version of the class loaded would be whichever plugin registered the autoloader first, and all subsequent instantiations of the class will use that version, with potentially unpredictable behaviour and missing functionality.    

## Use

Require as normal with Composer:

`composer require --dev brianhenryie/strauss`

and use `vendor/bin/strauss` to execute.

Or, download `strauss.phar` from [releases](https://github.com/BrianHenryIE/strauss/releases/), 

```shell
curl -o strauss.phar -L -C - https://github.com/BrianHenryIE/strauss/releases/download/0.11.0/strauss.phar
```

Then run it from the root of your project folder using `php strauss.phar`. 

Its use should be automated in Composer scripts. 

```json
"scripts": {
    "strauss": [
        "vendor/bin/strauss"
    ],
    "post-install-cmd": [
        "@strauss"
    ],
    "post-update-cmd": [
        "@strauss"
    ]
}
```

or

```json
"scripts": {
    "strauss": [
        "@php strauss.phar"
    ]
}
```

## Configuration

Strauss potentially requires zero configuration, but likely you'll want to customize a little, by adding in your `composer.json` an `extra/strauss` object. The following is the default config, where the `namespace_prefix` and `classmap_prefix` are determined from your `composer.json`'s `autoload` or `name` key and `packages` is determined from the `require` key:

```json
"extra": {
    "strauss": {
        "target_directory": "strauss",
        "namespace_prefix": "BrianHenryIE\\My_Project\\",
        "classmap_prefix": "BrianHenryIE_My_Project_",
        "constant_prefix": "BHMP_",
        "packages": [
        ],
        "override_autoload": {
        },
        "exclude_from_copy": {
            "packages": [
            ],
            "namespaces": [
            ],
            "file_patterns": [
            ]
        },
        "exclude_from_prefix": {
            "packages": [
            ],
            "namespaces": [
            ],
            "file_patterns": [
                "/^psr.*$/"
            ]
        },
        "namespace_replacement_patterns" : {
        },
        "delete_vendor_files": false
    }
},
```

The following configuration is inferred:

- `target_directory` defines the directory the files will be copied to
- `namespace_prefix` defines the default string to prefix each namespace with
- `classmap_prefix` defines the default string to prefix class names in the global namespace
- `packages` is the list of packages to process. If absent, all packages in the `require` key of your `composer.json` are included
- `classmap_output` is a `bool` to decide if Strauss will create `autoload-classmap.php` and `autoload.php`. If it is not set, it is `false` if `target_directory` is in your project's `autoload` key, `true` otherwise.

The following configuration is default:

- `delete_vendor_files`: `false` a boolean flag to indicate if files copied from the packages' vendor directories should be deleted after being processed. It defaults to false, so any destructive change is opt-in.
- `exclude_from_prefix` / [`file_patterns`](https://github.com/BrianHenryIE/strauss/blob/83484b79cfaa399bba55af0bf4569c24d6eb169d/src/ChangeEnumerator.php#L92-L96) : `[/psr.*/]` PSR namespaces are ignored by default for interoperability. If you override this key, be sure to include `/psr.*/` too.
- `include_modified_date` is a `bool` to decide if Strauss should include a date in the (phpdoc) header written to modified files. Defaults to `true`.
- `include_author` is a `bool` to decide if Strauss should include the author name in the (phpdoc) header written to modified files. Defaults to `true`.

The remainder is empty:

- `constant_prefix` is for `define( "A_CONSTANT", value );` -> `define( "MY_PREFIX_A_CONSTANT", value );`. If it is empty, constants are not prefixed (this may change to an inferred value).
- `override_autoload` a dictionary, keyed with the package names, of autoload settings to replace those in the original packages' `composer.json` `autoload` property.
- `exclude_from_copy` 
  - [`packages`](https://github.com/BrianHenryIE/strauss/blob/83484b79cfaa399bba55af0bf4569c24d6eb169d/src/FileEnumerator.php#L77-L79) array of package names to be skipped
  - [`namespaces`](https://github.com/BrianHenryIE/strauss/blob/83484b79cfaa399bba55af0bf4569c24d6eb169d/src/FileEnumerator.php#L95-L97) array of namespaces to skip (exact match from the package autoload keys)
  - [`file_patterns`](https://github.com/BrianHenryIE/strauss/blob/83484b79cfaa399bba55af0bf4569c24d6eb169d/src/FileEnumerator.php#L133-L137) array of regex patterns to check filenames against (including vendor relative path) where Strauss will skip that file if there is a match
- `exclude_from_prefix`
  - [`packages`](https://github.com/BrianHenryIE/strauss/blob/83484b79cfaa399bba55af0bf4569c24d6eb169d/src/ChangeEnumerator.php#L86-L90) array of package names to exclude from prefixing.
  - [`namespaces`](https://github.com/BrianHenryIE/strauss/blob/83484b79cfaa399bba55af0bf4569c24d6eb169d/src/ChangeEnumerator.php#L177-L181) array of exact match namespaces to exclude (i.e. not substring/parent namespaces)
- [`namespace_replacement_patterns`](https://github.com/BrianHenryIE/strauss/blob/83484b79cfaa399bba55af0bf4569c24d6eb169d/src/ChangeEnumerator.php#L183-L190) a dictionary to use in `preg_replace` instead of prefixing with `namespace_prefix`.

## Autoloading

Strauss uses Composer's own tools to generate a classmap file in the `target_directory` and creates an `autoload.php` alongside it, so in many projects autoloading is just a matter of: 

```php
require_once __DIR__ . '/strauss/autoload.php';
```

If you prefer to use Composer's autoloader, add your `target_directory` to the `classmap` and strauss will not create its own `autoload.php`. `psr-4` autoloading is not straightforward with Strauss's approach to copying files, so stick with Mozart for that.

```json
"autoload": {
    "classmap": [
        "src",
        "strauss"
    ]   
},
```


## Motivation & Comparison to Mozart

I was happy to make PRs to Mozart to fix bugs, but they weren't being reviewed and merged. At the time of writing, somewhere approaching 50% of Mozart's code [was written by me](https://github.com/coenjacobs/mozart/graphs/contributors) with an additional [nine open PRs](https://github.com/coenjacobs/mozart/pulls?q=is%3Apr+author%3ABrianHenryIE+) and the majority of issues' solutions [provided by me](https://github.com/coenjacobs/mozart/issues?q=is%3Aissue+). This fork is a means to merge all outstanding bugfixes I've written and make some more drastic changes I see as a better approach to the problem.

Benefits over Mozart:

* A single output directory whose structure matches source vendor directory structure (conceptually easier than Mozart's independent `classmap_directory` and `dep_directory`)
* A generated `autoload.php` to `include` in your project (analogous to Composer's `vendor/autoload.php`)  
* Handles `files` autoloaders – and any autoloaders that Composer itself recognises, since Strauss uses Composer's own tooling to parse the packages
* Zero configuration – Strauss infers sensible defaults from your `composer.json`
* No destructive defaults – `delete_vendor_files` defaults to `false`, so any destruction is explicitly opt-in
* Licence files are included and PHP file headers are edited to adhere to licence requirements around modifications. My understanding is that re-distributing code that Mozart has handled is non-compliant with most open source licences – illegal!
* Extensively tested – PhpUnit tests have been written to validate that many of Mozart's bugs are not present in Strauss
* More configuration options – allowing exclusions in copying and editing files, and allowing specific/multiple namespace renaming
* Respects `composer.json` `vendor-dir` configuration
* Prefixes constants (`define`)
* Handles meta-packages and virtual-packages

Strauss will read the Mozart configuration from your `composer.json` to enable a seamless migration.

## Changes before v1.0

* Comprehensive attribution of code forked from Mozart – changes have been drastic and `git blame` is now useless, so I intend to add more attributions
* More consistent naming. Are we prefixing or are we renaming?
* Further unit tests, particularly file-system related
* Regex patterns in config need to be validated

## Changes before v2.0

The correct approach to this problem is probably via [PHP-Parser](https://github.com/nikic/PHP-Parser/). At least all the tests will be useful. 

## Acknowledgements

[Coen Jacobs](https://github.com/coenjacobs/) and all the [contributors to Mozart](https://github.com/coenjacobs/mozart/graphs/contributors), particularly those who wrote nice issues.
