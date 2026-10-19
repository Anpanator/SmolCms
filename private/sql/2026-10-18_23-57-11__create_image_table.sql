-- Migration: create image table
CREATE TABLE IF NOT EXISTS image
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
