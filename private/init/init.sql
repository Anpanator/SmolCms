CREATE SCHEMA smolcms
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;

USE smolcms;

CREATE TABLE user
(
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    login_name      VARCHAR(255)                          NOT NULL UNIQUE KEY,
    password VARCHAR(128) NOT NULL,
    display_name    VARCHAR(255)                          NOT NULL,
    state           ENUM ('active', 'disabled', 'banned') NOT NULL,
    register_date   DATETIME                              NOT NULL,
    last_login_date DATETIME NULL,
    access_level    INT      NOT NULL
) ENGINE = InnoDb;

CREATE TABLE session
(
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(64) NOT NULL UNIQUE KEY,
    created    timestamp   NOT NULL DEFAULT current_timestamp,
    data       MEDIUMTEXT  NOT NULL
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

CREATE TABLE migration
(
    filename    VARCHAR(255) NOT NULL PRIMARY KEY,
    executed_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDb;

CREATE TABLE tag_category
(
    id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(64) NOT NULL UNIQUE KEY
) ENGINE = InnoDb;

INSERT IGNORE INTO tag_category (name) VALUES ('General'), ('Artist'), ('Copyright');

CREATE TABLE tag
(
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL UNIQUE KEY,
    category_id INT UNSIGNED NOT NULL,
    usage_count INT UNSIGNED NOT NULL DEFAULT 0,
    KEY idx_tag_name (name),
    KEY idx_tag_usage (usage_count DESC, name)
) ENGINE = InnoDb;

CREATE TABLE image_tag
(
    image_id INT UNSIGNED NOT NULL,
    tag_id   INT UNSIGNED NOT NULL,
    PRIMARY KEY (image_id, tag_id),
    KEY idx_image_tag_tag (tag_id)
) ENGINE = InnoDb;

INSERT IGNORE INTO tag (name, category_id)
SELECT 'tagme', id FROM tag_category WHERE name = 'General';

CREATE TABLE tag_suggestion
(
    id       INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    image_id INT UNSIGNED NOT NULL,
    tag_name VARCHAR(100) NOT NULL,
    user_id  INT UNSIGNED NOT NULL,
    created  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tag_suggestion_image_tag (image_id, tag_name),
    KEY idx_tag_suggestion_image (image_id),
    KEY idx_tag_suggestion_user (user_id)
) ENGINE = InnoDb;

CREATE TABLE tag_approval
(
    id      INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tag_id  INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    created DATETIME     NOT NULL,
    KEY idx_tag_approval_tag (tag_id),
    KEY idx_tag_approval_user (user_id)
) ENGINE = InnoDb;

CREATE TABLE audit_log
(
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    action_type INT          NOT NULL,
    context     VARCHAR(255) NOT NULL DEFAULT '',
    created     DATETIME     NOT NULL,
    KEY idx_audit_log_user (user_id)
) ENGINE = InnoDb;
