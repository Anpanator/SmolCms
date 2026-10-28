-- Migration: add the soft-delete marker used by the delete queue
--
-- SQLite cannot add an IF NOT EXISTS column. The table is rebuilt so this file
-- also stays safe when the schema was just initialized from init_sqlite.sql.
BEGIN TRANSACTION;

DROP TABLE IF EXISTS image_deleted_at_migration;

CREATE TABLE image_deleted_at_migration
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
    file_size          INTEGER      NULL,
    deleted_at         DATETIME     NULL
);

INSERT INTO image_deleted_at_migration
    (id, user_id, upload_date, status, checksum, original_extension, thumb_filename, sample_filename, download_filename, width, height, format, file_size)
SELECT id,
       user_id,
       upload_date,
       status,
       checksum,
       original_extension,
       thumb_filename,
       sample_filename,
       download_filename,
       width,
       height,
       format,
       file_size
FROM image;

DROP TABLE image;

ALTER TABLE image_deleted_at_migration RENAME TO image;

CREATE INDEX IF NOT EXISTS idx_image_deleted_at ON image (deleted_at);

COMMIT;
