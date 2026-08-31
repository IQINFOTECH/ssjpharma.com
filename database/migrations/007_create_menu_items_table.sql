-- Phase 1: menu items. Supports nesting (parent_id self-FK), page references or
-- explicit/external URLs, ordering, visibility and new-tab.
CREATE TABLE IF NOT EXISTS `menu_items` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `menu_id`     BIGINT UNSIGNED NOT NULL,
    `parent_id`   BIGINT UNSIGNED NULL DEFAULT NULL,
    `label`       VARCHAR(150)    NOT NULL,
    `page_id`     BIGINT UNSIGNED NULL DEFAULT NULL,   -- internal link target
    `url`         VARCHAR(500)    NULL DEFAULT NULL,    -- explicit/relative or external URL
    `is_external` TINYINT(1)      NOT NULL DEFAULT 0,
    `open_new_tab`TINYINT(1)      NOT NULL DEFAULT 0,
    `sort_order`  INT             NOT NULL DEFAULT 0,
    `is_active`   TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_items_menu_order` (`menu_id`, `sort_order`),
    KEY `idx_items_parent` (`parent_id`),
    KEY `idx_items_active` (`is_active`),
    CONSTRAINT `fk_items_menu`   FOREIGN KEY (`menu_id`)   REFERENCES `menus` (`id`)      ON DELETE CASCADE,
    CONSTRAINT `fk_items_parent` FOREIGN KEY (`parent_id`) REFERENCES `menu_items` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_items_page`   FOREIGN KEY (`page_id`)   REFERENCES `pages` (`id`)      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
