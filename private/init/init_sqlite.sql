CREATE TABLE user
(
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    login_name      VARCHAR(255) NOT NULL UNIQUE,
    password        VARCHAR(128) NOT NULL,
    display_name    VARCHAR(255) NOT NULL,
    state           TEXT         NOT NULL CHECK (state IN ('active', 'disabled', 'banned')),
    register_date   DATETIME     NOT NULL,
    last_login_date DATETIME NULL,
    access_level    INTEGER  NOT NULL
);

CREATE TABLE session
(
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    session_id VARCHAR(64) NOT NULL UNIQUE,
    created    DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    data       TEXT        NOT NULL
);

CREATE TABLE article
(
    id      INTEGER PRIMARY KEY AUTOINCREMENT,
    slug    VARCHAR(255) NOT NULL UNIQUE,
    title   VARCHAR(255) NOT NULL,
    state   TEXT         NOT NULL CHECK (state IN ('online', 'draft')),
    content TEXT         NOT NULL,
    created DATETIME     NOT NULL,
    updated DATETIME     NOT NULL
);

CREATE TABLE migration
(
    filename    VARCHAR(255) NOT NULL PRIMARY KEY,
    executed_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE image
(
    id                 INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id            INTEGER      NOT NULL,
    upload_date        DATETIME     NOT NULL,
    status             TEXT         NOT NULL DEFAULT 'pending'
        CHECK (status IN ('pending', 'pending_review', 'accepted', 'rejected')),
    checksum           VARCHAR(64)  NOT NULL,
    original_extension VARCHAR(16)  NOT NULL,
    thumb_filename     VARCHAR(255) NULL,
    sample_filename    VARCHAR(255) NULL,
    download_filename  VARCHAR(255) NULL,
    width              INTEGER      NULL,
    height             INTEGER      NULL,
    format             VARCHAR(16)  NULL,
    file_size          INTEGER      NULL
);
