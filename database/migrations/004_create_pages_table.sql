-- Phase 1: CMS pages. Content is composed from modular page_sections; the
-- optional `content` column is a simple rich-text fallback. Statuses:
-- draft | published | archived. Soft delete via deleted_at.
CREATE TABLE IF NOT EXISTS `pages` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`             VARCHAR(200)    NOT NULL,
    `slug`              VARCHAR(200)    NOT NULL,
    `status`            VARCHAR(20)     NOT NULL DEFAULT 'draft',   -- draft|published|archived
    `template`          VARCHAR(60)     NOT NULL DEFAULT 'default',
    `content`           LONGTEXT        NULL DEFAULT NULL,          -- optional simple body (sanitised)
    `is_home`           TINYINT(1)      NOT NULL DEFAULT 0,
    `meta_title`        VARCHAR(255)    NULL DEFAULT NULL,
    `meta_description`  VARCHAR(320)    NULL DEFAULT NULL,
    `canonical_url`     VARCHAR(255)    NULL DEFAULT NULL,
    `robots`            VARCHAR(60)     NULL DEFAULT NULL,          -- e.g. index,follow
    `og_image_id`       BIGINT UNSIGNED NULL DEFAULT NULL,
    `featured_image_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `published_at`      TIMESTAMP       NULL DEFAULT NULL,
    `created_by`        BIGINT UNSIGNED NULL DEFAULT NULL,
    `updated_by`        BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`        TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_pages_slug` (`slug`),
    KEY `idx_pages_status` (`status`),
    KEY `idx_pages_home` (`is_home`),
    KEY `idx_pages_deleted` (`deleted_at`),
    CONSTRAINT `fk_pages_og`       FOREIGN KEY (`og_image_id`)       REFERENCES `media` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_pages_featured` FOREIGN KEY (`featured_image_id`) REFERENCES `media` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_pages_creator`  FOREIGN KEY (`created_by`)        REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_pages_updater`  FOREIGN KEY (`updated_by`)        REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
