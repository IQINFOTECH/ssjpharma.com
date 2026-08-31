-- Phase 1: modular page sections. `type` selects the renderer/component; `data`
-- is a typed JSON payload the admin edits. New section types can be added later
-- with zero schema change (open/closed by design).
CREATE TABLE IF NOT EXISTS `page_sections` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `page_id`     BIGINT UNSIGNED NOT NULL,
    `type`        VARCHAR(40)     NOT NULL,   -- hero|richtext|image_text|cards|cta|faq|stats|
                                              -- product_showcase|testimonials|contact_cta
    `data`        JSON            NULL,
    `sort_order`  INT             NOT NULL DEFAULT 0,
    `is_visible`  TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_sections_page_order` (`page_id`, `sort_order`),
    KEY `idx_sections_type` (`type`),
    CONSTRAINT `fk_sections_page` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
