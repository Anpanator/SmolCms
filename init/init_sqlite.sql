CREATE TABLE IF NOT EXISTS user
(
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    login_name      VARCHAR(255) NOT NULL UNIQUE,
    password        VARCHAR(128) NOT NULL,
    display_name    VARCHAR(255) NOT NULL,
    state           TEXT         NOT NULL CHECK (state IN ('active', 'disabled', 'banned')),
    register_date   DATETIME     NOT NULL,
    last_login_date DATETIME     NULL
);

CREATE TABLE IF NOT EXISTS session
(
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    session_id VARCHAR(64) NOT NULL UNIQUE,
    created    DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    data       TEXT        NOT NULL
);

CREATE TABLE IF NOT EXISTS article
(
    id      INTEGER PRIMARY KEY AUTOINCREMENT,
    slug    VARCHAR(255) NOT NULL UNIQUE,
    title   VARCHAR(255) NOT NULL,
    state   TEXT         NOT NULL CHECK (state IN ('online', 'draft')),
    content TEXT         NOT NULL,
    created DATETIME     NOT NULL,
    updated DATETIME     NOT NULL
);
