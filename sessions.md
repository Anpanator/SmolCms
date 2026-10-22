# Session persistence across container redeploys

Commit: `24541f2` (`Keep sessions valid across redeploys`)

## Summary

User sessions are stored in SQLite, and the Docker setup already bind-mounts the
database directory from the host. A container replacement therefore did not
delete the `session` rows by itself.

The real failure mode was session garbage collection:

- PHP's default `session.gc_maxlifetime` is 1440 seconds (24 minutes).
- The custom session handler only stored the session's creation time and never
  refreshed it.
- Garbage collection deletes rows whose `created` value is older than
  `gc_maxlifetime`. Since `created` stayed at login time, an active session
  could be deleted after 24 minutes even while the user was still using it.
- The session cookie, by contrast, lasts 24 hours.

This made sessions appear to vanish around redeploys: the database survived the
container replacement, but old rows were still subject to the 24-minute GC
window on later requests. The fix keeps the database record alive for the same
24 hours as the cookie, measured from the last activity.

## How the session flow works

1. `RegisterSessionHandlerStartupAction` registers `SessionHandler` as PHP's
   session save handler.
2. `ResumeSessionStartupAction` looks for the `session` cookie and validates its
   ID against the database before resuming it.
3. `SessionService` starts the session with the configured cookie and
   garbage-collection options.
4. `SessionHandler` reads and writes `SessionEntity` rows through
   `SessionEntityService`.
5. The SQLite file lives at `private/db/smolcms.sq3`.
6. In Docker, `~/SmolCms/db` is bind-mounted to `/var/www/html/private/db`, so
   the file, including user and session rows, outlives the container.
7. The container entrypoint runs `--init-db` without `--reset`, which only
   creates tables in an empty database and never drops existing data.

## Changes in detail

### `src/Service/Core/Session/SessionService.php`

Added one lifetime constant and used it for both the cookie and server-side
garbage collection:

```php
private const int SESSION_LIFETIME = 24 * 60 * 60;
private const SESSION_OPTIONS = [
    'name' => self::SESSION_NAME,
    'cookie_lifetime' => self::SESSION_LIFETIME,
    'gc_maxlifetime' => self::SESSION_LIFETIME,
    // ...
];
```

Before this change, `gc_maxlifetime` came from PHP's default of 1440 seconds.
Now the database session lifetime matches the browser cookie lifetime. PHP
applies `gc_maxlifetime` when `session_start()` is called with these options.

### `src/Service/Core/Session/SessionHandler.php`

`write()` now refreshes the stored timestamp whenever an existing session is
written:

```php
if ($session) {
    $session->setData($data);
    $session->setCreated(new DateTime());
}
```

`updateTimestamp()` was previously a no-op. It now delegates to the entity
service:

```php
public function updateTimestamp(string $id, string $data): bool
{
    return $this->sessionEntityService->touchBySessionId($id);
}
```

PHP calls `updateTimestamp()` when a session was read but its data did not
change. This covers ordinary read-only requests, so browsing the application
keeps the session alive even without changing session values.

### `src/Service/DB/SessionEntityService.php`

Added `touchBySessionId()`:

```php
public function touchBySessionId(string $sessionId): bool
{
    $session = $this->findOneBySessionId($sessionId);
    if ($session === null) {
        return false;
    }

    $session->setCreated(new DateTime());
    $this->saveOrUpdate($session);
    return true;
}
```

It returns `false` when the session no longer exists, which lets PHP's session
handler report that the timestamp could not be updated.

The database access remains inside the entity service and uses the existing
`QueryCriteria`-based entity methods.

### `src/Data/Persistence/SessionEntity.php`

No schema change was necessary. The existing `created` column now doubles as
the last-activity timestamp, and a comment documents that behavior:

```php
// Also refreshed on activity; garbage collection treats it as the last-seen timestamp.
private ?DateTime $created,
```

This avoids an `ALTER TABLE` migration for existing deployments. The trade-off
is that the column name is historical: it means "created or last active", not
strictly "created".

### Garbage collection

`SessionEntityService::deleteOlderThan()` already deleted sessions whose
`created` value was older than the supplied lifetime. Because writes and
`updateTimestamp()` now refresh that value, the query now means "delete
sessions inactive for the configured lifetime".

## Why redeploys previously appeared to log users out

The Docker database bind mount was already correct, so a redeploy preserved the
rows. The problem was the combination below:

- A session created at login was eligible for deletion 24 minutes later.
- Garbage collection runs probabilistically on session start (1 in 100 requests
  in the container configuration by default).
- If a user logged in, used the application for a while, and then redeployed,
  the next requests after the redeploy could trigger GC and delete their
  still-in-use session.

After the change:

- Every request refreshes the stored activity time.
- GC removes a session only after 24 hours without activity.
- The server-side lifetime and the cookie lifetime are identical.

## Tests added

### `tests/Service/Core/Session/SessionHandlerTest.php`

- `testWrite_RefreshesActivityTimestamp`: writes to a session with an old
  timestamp and asserts that the stored time is refreshed.
- `testUpdateTimestamp_RefreshesActivityTimestamp`: verifies the
  read-without-change path used by PHP.
- `testUpdateTimestamp_ReturnsFalseForMissingSession`: verifies the missing-row
  behavior.

### `tests/Service/Core/Session/SessionServiceTest.php`

- `testSessionOptions_KeepServerSideSessionsAsLongAsTheCookie`: asserts that
  `gc_maxlifetime` exists, equals the cookie lifetime, and is 24 hours.

### `tests/Service/DB/SessionEntityServiceTest.php`

- `testTouchBySessionId_refreshesActivityAndReportsMissingSessions`: covers the
  entity service method directly.

### `tests/Config/DockerPersistenceTest.php`

- Asserts that `docker/compose.yaml` bind-mounts `${HOME}/SmolCms/db` to
  `/var/www/html/private/db`.
- Asserts that the container entrypoint runs `--init-db` without `--reset`, so
  starting a replacement container cannot wipe existing session rows.

## Documentation

- `docs/session-handling.adoc` now describes activity-based expiry and the
  matching cookie/GC lifetimes.
- `docker/README.md` now states that accounts and sessions live in the
  persisted SQLite database, so replacing the container does not log users out.

## Verification

- Focused session tests: 24 tests, 82 assertions.
- Full suite: 745 tests, 51,634 assertions, all passing.
- Manual Docker check:
  1. Logged in through the deployed application.
  2. Confirmed the session row in `~/SmolCms/db/smolcms.sq3`.
  3. Force-recreated the `smolcms-dev` container with Compose.
  4. Reused the original session cookie and received the authenticated
     `/api/session` response.
  5. Confirmed that a normal request refreshed the stored `created` timestamp.

## Remaining limitations

- Sessions still expire after 24 hours without activity, which matches the
  browser cookie.
- Removing `~/SmolCms/db`, running `--init-db --reset`, or explicitly deleting
  the database still destroys sessions.
- The session cookie still lacks the `Secure` flag; HTTPS deployments should
  set it.
- Session locking is still not implemented, and session IDs are not regenerated
  on login.
