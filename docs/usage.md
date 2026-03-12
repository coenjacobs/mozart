# Usage

## Automating with Composer scripts

Mozart is designed to install and be forgotten about. Using Composer scripts, Mozart can run automatically whenever Composer installs or updates a package. This ensures that bundled packages are always up to date:

```json
"scripts": {
    "post-install-cmd": [
        "\"vendor/bin/mozart\" compose"
    ],
    "post-update-cmd": [
        "\"vendor/bin/mozart\" compose"
    ]
}
```

When using Mozart through Docker, replace the `"\"vendor/bin/mozart\" compose"` lines with the Docker run command for your project. Running Mozart from inside the Docker container is fast and should only take a couple of seconds.

## Recommended integration: generated autoloader

With `generate_autoloader` enabled, Mozart writes a Composer-compatible autoloader inside `dep_directory`. This is the recommended integration path for 1.2.0 and later, and it is enabled by default.

Include the generated autoloader from your plugin bootstrap:

```php
require_once __DIR__ . '/vendor-prefixed/autoload.php';
```

If you customize `dep_directory`, update the path accordingly. This generated autoloader handles the prefixed PSR-4, classmap, and `files` entries for your bundled dependencies, so you do not need to mirror those directories in your project's own `composer.json`.

## Manual Composer autoload integration

If you disable `generate_autoloader`, fall back to wiring the output directories into your project's `composer.json`:

```json
{
    "autoload": {
        "psr-4": {
            "CoenJacobs\\TestProject\\": "src/"
        },
        "classmap": [
            "classes/dependencies/"
        ]
    },
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
    }
}
```

The `dep_directory` is a subdirectory of `src/`, which is already mapped via PSR-4. Since `dep_namespace` falls under the existing `CoenJacobs\TestProject\` namespace, Composer resolves it automatically — no additional PSR-4 entry is needed. The `classmap_directory` is added to the `classmap` array so classmap dependencies are discovered by Composer's autoloader.

After running Mozart in this manual mode, run `composer dump-autoload` to regenerate Composer's autoloader with the new paths. If you encounter memory issues during Mozart's AST processing, see [memory.md](memory.md) for how to increase the limit.
