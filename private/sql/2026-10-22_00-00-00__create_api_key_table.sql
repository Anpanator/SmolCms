-- Migration: create API key storage
CREATE TABLE IF NOT EXISTS api_key
(
    id       INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id  INTEGER      NOT NULL UNIQUE,
    key_hash VARCHAR(64)  NOT NULL UNIQUE,
    created  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
);
