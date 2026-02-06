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
