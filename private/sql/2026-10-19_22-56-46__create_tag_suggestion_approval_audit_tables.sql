-- Migration: create tag suggestion approval audit tables
CREATE TABLE IF NOT EXISTS tag_suggestion
(
    id       INTEGER PRIMARY KEY AUTOINCREMENT,
    image_id INTEGER      NOT NULL,
    tag_name VARCHAR(100) NOT NULL COLLATE NOCASE,
    user_id  INTEGER      NOT NULL,
    created  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (image_id, tag_name)
);

CREATE INDEX IF NOT EXISTS idx_tag_suggestion_image ON tag_suggestion (image_id);
CREATE INDEX IF NOT EXISTS idx_tag_suggestion_user ON tag_suggestion (user_id);

CREATE TABLE IF NOT EXISTS tag_approval
(
    id      INTEGER PRIMARY KEY AUTOINCREMENT,
    tag_id  INTEGER  NOT NULL,
    user_id INTEGER  NOT NULL,
    created DATETIME NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_tag_approval_tag ON tag_approval (tag_id);
CREATE INDEX IF NOT EXISTS idx_tag_approval_user ON tag_approval (user_id);

CREATE TABLE IF NOT EXISTS audit_log
(
    id          INTEGER      PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER      NOT NULL,
    action_type INTEGER      NOT NULL,
    context     VARCHAR(255) NOT NULL DEFAULT '',
    created     DATETIME     NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_audit_log_user ON audit_log (user_id);
