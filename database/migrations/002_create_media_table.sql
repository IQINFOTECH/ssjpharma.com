-- Phase 1: media library. Files are stored under public/uploads with generated
-- names; PHP execution there is disabled by .htaccess (SECURITY_PLAN §9).
CREATE TABLE IF NOT EXISTS `media` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `disk_path`      VARCHAR(255)    NOT NULL,               -- absolute-ish path under public/uploads
    `url_path`       VARCHAR(255)    NOT NULL,               -- web path e.g. /uploads/2026/08/x.jpg
    `original_name`  VARCHAR(255)    NOT NULL,
    `mime`           VARCHAR(120)    NOT NULL,
    `extension`      VARCHAR(16)     NOT NULL,
    `size_bytes`     BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `width`          INT UNSIGNED    NULL DEFAULT NULL,
    `height`         INT UNSIGNED    NULL DEFAULT NULL,
    `alt_text`       VARCHAR(255)    NULL DEFAULT NULL,
    `title`          VARCHAR(255)    NULL DEFAULT NULL,
    `is_private`     TINYINT(1)      NOT NULL DEFAULT 0,     -- reserved for protected files
    `uploaded_by`    BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at`     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`     TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_media_mime` (`mime`),
    KEY `idx_media_created` (`created_at`),
    KEY `idx_media_deleted` (`deleted_at`),
    CONSTRAINT `fk_media_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
