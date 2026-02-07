# Usage

## Automating with Composer scripts

Mozart is designed to install and be forgotten about. Using Composer scripts, Mozart can run automatically whenever Composer installs or updates a package. This ensures that bundled packages are always up to date:

```json
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

When using Mozart through Docker, replace the `"\"vendor/bin/mozart\" compose"` lines with the Docker run command for your project. Running Mozart from inside the Docker container is fast and should only take a couple of seconds.

## Configuring your project's autoloader

Since Mozart processes the full dependency tree, packages may use any combination of PSR-4 and classmap autoloading. Your project's `composer.json` must include autoload entries for both the `dep_directory` and `classmap_directory`:

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

After running Mozart, run `composer dump-autoload` to regenerate the autoloader with the new paths.
