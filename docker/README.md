# Docker images

## Build dependencies

- Docker Engine with a reachable daemon and Docker Compose v2; Compose builds
  the Dockerfile target used by the development service.
- POSIX shell, GNU coreutils, and findutils for `deploy.sh` (`stat`, `chgrp`, `chmod`, `find`).
- Network access to pull `php:8.5-fpm` and `composer:2` and install build packages.

The Dockerfile builds `dev` and `prod` targets from the same Nginx/PHP-FPM
base. Both use the unprivileged `10001:10001` account and listen on container
port `8080`. The supplied Compose service runs only the `dev` target with a
read-only root filesystem and writable bind mounts for database, uploads,
images, public thumbnails, and logs. The `prod` target disables PHP
`display_errors`; it does not include a production Compose deployment and is
not a production-hardening guarantee.

## Local development

Run from the project root:

```sh
./deploy.sh
```

The script selects the `rootless` Docker context when available (unless
`DOCKER_CONTEXT` or `DOCKER_HOST` is set), prepares writable host state
directories, then uses Compose to build the `dev` target and start
`smolcms-dev` at <http://localhost:8080>. The port binds to localhost only;
set `SMOLCMS_DEV_PORT` to change it.

SQLite data, uploads, generated images, and logs persist below `~/SmolCms`
(`db`, `upload`, `img`, `public-img`, and `logs`). User accounts and sessions
live in the SQLite database, so replacing the container does not log users out.
The script creates these directories with setgid group-write permissions and repairs group permissions
on existing host-owned data for the rootless container user. The development
image enables `display_errors`.

On startup the container initializes an empty database, applies pending
migrations, and starts a background worker that processes up to 100 pending
images periodically. Nginx and PHP errors are written to the mounted logs
directory.

After preparing the host directories, Compose can also be run directly with:

```sh
docker compose -f docker/compose.yaml up --build -d
```
