# Docker

Mozart provides Docker images for multiple architectures (linux/amd64, linux/arm64, and linux/arm/v7), making it compatible with Intel/AMD systems, Apple Silicon Macs, ARM-based servers, and Raspberry Pi devices (including Raspberry Pi 3 and earlier).

## Image registries

Mozart images are available from two registries:

- **Docker Hub**: `coenjacobs/mozart`
- **GitHub Container Registry**: `ghcr.io/coenjacobs/mozart`

## Tag strategy

- **`latest`**: Points to the highest stable version (e.g., `1.0.0`). This is the recommended tag for production use.
- **`dev`**: Points to the latest commit on the `master` branch. Use this for testing bleeding-edge features.
- **Version tags**: Specific versions like `1.0.0`, `1.0.0-beta.1`, etc. Use these for reproducible builds.
- **Version aliases**: Shortcuts like `1` (latest 1.x.x) and `1.0` (latest 1.0.x) are available for convenience.

## Pulling images

**From Docker Hub:**
```
docker pull coenjacobs/mozart
```

**From GitHub Container Registry:**
```
docker pull ghcr.io/coenjacobs/mozart
```

**Pull a specific version:**
```
docker pull coenjacobs/mozart:1.0.0
```

**Pull development build:**
```
docker pull coenjacobs/mozart:dev
```

You can see [all available tags on Docker Hub](https://hub.docker.com/r/coenjacobs/mozart/tags) or [on GitHub Container Registry](https://github.com/coenjacobs/mozart/pkgs/container/mozart).

## Running Mozart

Start the container and run the `mozart compose` command in a single command:

```
docker run --rm -it -v ${PWD}:/project/ coenjacobs/mozart /mozart/bin/mozart compose
```

This command automatically adds the current working directory as a volume into the designated directory for the project: `/project/`. In the Docker container, Mozart is installed in the `/mozart/` directory. Using the above command will run Mozart on the current working directory.

## Dockerfile stages

The Dockerfile uses multi-stage builds. Each stage serves a different purpose:

| Stage | Base | Purpose |
|---|---|---|
| `base` | `php:${PHP_VERSION}-cli-alpine` | Bare PHP CLI image, shared by all stages |
| `builder` | `base` | Adds git, Composer, installs all dependencies. Used for CI testing. |
| `develop` | `builder` | Adds Xdebug for local development and debugging |
| `packager` | `builder` | Reinstalls dependencies with `--no-dev` for production |
| `application` | `base` | Final production image. Copies only built artifacts from `packager`. |

The `PHP_VERSION` build arg defaults to `8.5` and is overridden by CI to test against multiple PHP versions (8.2 through 8.5).

## Docker Compose services

`docker-compose.yml` defines two services for development and testing:

| Service | Dockerfile target | Volume mount | Xdebug | Use case |
|---|---|---|---|---|
| `builder` | `develop` | Yes (`.:/mozart/`) | Yes | Local development — code changes reflected immediately |
| `actions-tester` | `builder` | No | No | CI simulation — files copied at build time |

The `builder` service is what you use for local development. It mounts the working directory so edits are reflected without rebuilding. The `actions-tester` service mirrors how CI runs tests: files are baked into the image at build time, so you must rebuild (`docker compose build actions-tester`) after code changes.

Both services accept a `PHP_VERSION` environment variable (defaults to `8.5`).

## PHP configuration

The `docker/php/` directory contains PHP configuration overrides:

- `error_reporting.ini` — Sets `error_reporting=E_ALL`
- `xdebug.ini` — Configures Xdebug with `mode=develop`, connecting to `host.docker.internal` (only loaded in the `develop` stage)

## See also

- [testing.md](testing.md) — Running tests with Docker
