-- Additive migration. Run once before enabling image uploads.
-- Existing noticias.imagen paths and repository images remain untouched.
-- Immutability makes public image caching safe across new deployments.
CREATE TABLE IF NOT EXISTS app_news_images (
    id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    mime_type VARCHAR(32) CHARACTER SET ascii NOT NULL,
    byte_size INT UNSIGNED NOT NULL,
    sha256 CHAR(64) CHARACTER SET ascii NOT NULL,
    image_data MEDIUMBLOB NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- News deletion/replacement intentionally retains historical images.
-- Clean up unreferenced images only through a separate, backed-up maintenance
-- operation. Do not delete them automatically: old public URLs may still be used.
