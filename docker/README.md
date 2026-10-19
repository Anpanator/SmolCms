# Docker images

## Build dependencies

- **Docker engine** with a reachable daemon — rootless Docker on this machine (`deploy.sh` selects the `rootless`
  context when available; `DOCKER_CONTEXT`/`DOCKER_HOST` override it).
- **Docker Compose v2** CLI plugin (`docker compose`) — `deploy.sh` delegates building and running to Compose.
- **Docker Buildx** CLI plugin (`docker buildx`) — required for BuildKit builds; without it Compose falls back to the
  deprecated classic builder.
- **POSIX shell and GNU coreutils** for `deploy.sh` (`stat`, `chgrp`, `chmod`).
- Base images pulled during the build: `php:8.5-fpm` and `composer:2`.

Both image targets run Nginx and PHP-FPM as the unprivileged `10001:10001` user, listen on container port `8080`, and
mount writable data paths separately from the read-only application image. The Docker build copies only the application
runtime files into `/var/www/html`; the `docker/` and `docs/` directories are not deployed into the application tree.
The `prod` target (display errors disabled) remains in the Dockerfile for when a production deployment becomes a
requirement.

## Local development

Run from the project root:

```sh
./deploy.sh
```

The script prepares the host state directories, then uses Docker Compose v2 (BuildKit) to build the `smolcms:dev`
target and start the `smolcms-dev` container at <http://localhost:8080>. The port is bound to localhost only; set
`SMOLCMS_DEV_PORT` to change it.
SQLite, uploaded images, and logs persist in bind-mounted directories under `~/SmolCms` (`db`, `upload`, `img`,
`public-img`, `logs`); the script creates them with setgid group-write permissions so the rootless Docker user namespace
can write while the data stays host-accessible. `display_errors` is enabled in this image.
On startup the container initializes the database, runs pending migrations, and starts a background loop that processes
pending image uploads every minute (up to 100 per run).

The script uses a working Docker context named `rootless` when available. Compose can also be invoked directly with
`docker compose -f docker/compose.yaml up --build -d` after preparing the host directories as above.
