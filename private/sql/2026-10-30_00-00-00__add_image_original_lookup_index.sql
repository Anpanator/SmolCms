CREATE INDEX IF NOT EXISTS idx_image_original_checksum_extension
    ON image (checksum, original_extension);
