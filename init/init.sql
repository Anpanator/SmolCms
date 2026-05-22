CREATE SCHEMA smolcms
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;

USE smolcms;

CREATE TABLE IF NOT EXISTS user
(
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    login_name      VARCHAR(255)                          NOT NULL UNIQUE KEY,
    password VARCHAR(128) NOT NULL,
    display_name    VARCHAR(255)                          NOT NULL,
    state           ENUM ('active', 'disabled', 'banned') NOT NULL,
    register_date   DATETIME                              NOT NULL,
    last_login_date DATETIME                              NULL
) ENGINE = InnoDb;

CREATE TABLE IF NOT EXISTS session
(
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(64) NOT NULL UNIQUE KEY,
    created    timestamp   NOT NULL DEFAULT current_timestamp,
    data       MEDIUMTEXT  NOT NULL
) ENGINE = InnoDb;

CREATE TABLE IF NOT EXISTS article
(
    id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug    VARCHAR(255)             NOT NULL UNIQUE KEY,
    title   VARCHAR(255)             NOT NULL,
    state   ENUM ('online', 'draft') NOT NULL,
    content MEDIUMTEXT               NOT NULL,
    created DATETIME                 NOT NULL,
    updated DATETIME                 NOT NULL
) ENGINE = InnoDb;