CREATE SCHEMA smolcms
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;

USE
    smolcms;

CREATE TABLE user
(
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    login_name      VARCHAR(255)                          NOT NULL UNIQUE KEY,
    display_name    VARCHAR(255)                          NOT NULL,
    state           ENUM ('active', 'disabled', 'banned') NOT NULL,
    register_date   DATETIME                              NOT NULL,
    last_login_date DATETIME                              NULL
) ENGINE = InnoDb;

CREATE TABLE article
(
    id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug    VARCHAR(255)             NOT NULL UNIQUE KEY,
    title   VARCHAR(255)             NOT NULL,
    state   ENUM ('online', 'draft') NOT NULL,
    content MEDIUMTEXT               NOT NULL,
    created DATETIME                 NOT NULL,
    updated DATETIME                 NOT NULL
) ENGINE = InnoDb;