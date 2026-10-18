# Docker images

Both targets run Nginx and PHP-FPM as the unprivileged `10001:10001` user, listen on container port `8080`, and mount
writable data paths separately from the read-only application image. The Docker build copies only the application
runtime files into `/var/www/html`; the `docker/` and `docs/` directories are not deployed into the application tree.

## Local development

Run from the project root:

```sh
./deploy.sh
```

The script builds the `smolcms:dev` target and runs it at <http://localhost:8080>. The port is bound to localhost only.
SQLite and uploaded images persist in rootless Docker volumes; logs are written to `~/SmolCms/logs` on the host.
`display_errors` is enabled in this image.

The script uses a working Docker context named `rootless` when available. Set `DOCKER_CONTEXT` or `DOCKER_HOST` to
select another daemon explicitly.

## Production image

Run:

```sh
./docker/run-prod.sh
```

This builds and runs the `smolcms:prod` target with `display_errors` disabled. Host directories are created under
`~/SmolCms` and mounted as follows:

| Host path          | Container path                 |
|--------------------|--------------------------------|
| `~/SmolCms/db`     | `/var/www/html/private/db`     |
| `~/SmolCms/upload` | `/var/www/html/private/upload` |
| `~/SmolCms/logs`   | `/var/www/html/private/logs`   |

The production script sets the bind-mount directories to the current user's primary group with setgid group-write
permissions. This allows the non-root container user to write through a rootless Docker user namespace while keeping the
data accessible to the host user. Use the rootless Docker context owned by the same host user. The application runs
`php src/app.php --init-db` on startup, initializing the SQLite schema only when the mounted database has no tables; it
does not reset an existing database.

Both variants use host port `8080` by default and cannot run simultaneously on that port. Set `SMOLCMS_DEV_PORT` or
`SMOLCMS_PROD_PORT` to use another localhost port. The Compose profiles can also be invoked directly with
`docker compose -f docker/compose.yaml --profile dev up --build -d` or `--profile prod` after preparing the host
directories as above.
