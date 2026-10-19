-- Migration: create tag tables
CREATE TABLE IF NOT EXISTS tag_category
(
    id   INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(64) NOT NULL UNIQUE COLLATE NOCASE
);

INSERT OR IGNORE INTO tag_category (name) VALUES ('General'), ('Artist'), ('Copyright');

CREATE TABLE IF NOT EXISTS tag
(
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        VARCHAR(100) NOT NULL UNIQUE COLLATE NOCASE,
    category_id INTEGER NOT NULL,
    usage_count INTEGER NOT NULL DEFAULT 0
);

CREATE INDEX IF NOT EXISTS idx_tag_name ON tag (name);
CREATE INDEX IF NOT EXISTS idx_tag_usage ON tag (usage_count DESC, name);

CREATE TABLE IF NOT EXISTS image_tag
(
    image_id INTEGER NOT NULL,
    tag_id   INTEGER NOT NULL,
    PRIMARY KEY (image_id, tag_id)
);

CREATE INDEX IF NOT EXISTS idx_image_tag_tag ON image_tag (tag_id);

INSERT OR IGNORE INTO tag (name, category_id)
SELECT 'tagme', id FROM tag_category WHERE name = 'General';
