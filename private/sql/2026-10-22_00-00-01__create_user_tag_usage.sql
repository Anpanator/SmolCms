CREATE TABLE user_tag_usage
(
    user_id      INTEGER      NOT NULL,
    tag_id       INTEGER      NOT NULL,
    usage_count  INTEGER      NOT NULL DEFAULT 0,
    last_used_at DATETIME     NULL,
    PRIMARY KEY (user_id, tag_id)
);

CREATE INDEX idx_user_tag_usage_user_count
    ON user_tag_usage (user_id, usage_count DESC, tag_id);

CREATE INDEX idx_user_tag_usage_user_recent
    ON user_tag_usage (user_id, last_used_at DESC, tag_id);
