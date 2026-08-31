-- Phase 3: product catalog schema. Reuses `media` for physical files (secure
-- upload already implemented) via link tables. Relationships over repeated text.
-- Statuses: draft | published | archived. Soft delete via deleted_at. `is_demo`
-- flags clearly-marked development records (§32). Portable DDL (MySQL & MariaDB).

-- --- Dosage forms (managed, not hardcoded) ----------------------------------
CREATE TABLE IF NOT EXISTS `dosage_forms` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(80)     NOT NULL,
    `slug`       VARCHAR(90)     NOT NULL,
    `is_active`  TINYINT(1)      NOT NULL DEFAULT 1,
    `sort_order` INT             NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_dosage_slug` (`slug`),
    KEY `idx_dosage_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- Product categories (nestable) ------------------------------------------
CREATE TABLE IF NOT EXISTS `product_categories` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `parent_id`        BIGINT UNSIGNED NULL DEFAULT NULL,
    `name`             VARCHAR(150)    NOT NULL,
    `slug`             VARCHAR(180)    NOT NULL,
    `description`      TEXT            NULL DEFAULT NULL,
    `image_id`         BIGINT UNSIGNED NULL DEFAULT NULL,
    `meta_title`       VARCHAR(255)    NULL DEFAULT NULL,
    `meta_description` VARCHAR(320)    NULL DEFAULT NULL,
    `status`           VARCHAR(20)     NOT NULL DEFAULT 'draft',
    `sort_order`       INT             NOT NULL DEFAULT 0,
    `is_demo`          TINYINT(1)      NOT NULL DEFAULT 0,
    `created_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`       TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_pcat_slug` (`slug`),
    KEY `idx_pcat_parent` (`parent_id`),
    KEY `idx_pcat_status` (`status`),
    KEY `idx_pcat_sort` (`sort_order`),
    CONSTRAINT `fk_pcat_parent` FOREIGN KEY (`parent_id`) REFERENCES `product_categories` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_pcat_image`  FOREIGN KEY (`image_id`)  REFERENCES `media` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- Therapeutic areas ------------------------------------------------------
CREATE TABLE IF NOT EXISTS `therapeutic_areas` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`             VARCHAR(150)    NOT NULL,
    `slug`             VARCHAR(180)    NOT NULL,
    `description`      TEXT            NULL DEFAULT NULL,
    `image_id`         BIGINT UNSIGNED NULL DEFAULT NULL,
    `meta_title`       VARCHAR(255)    NULL DEFAULT NULL,
    `meta_description` VARCHAR(320)    NULL DEFAULT NULL,
    `status`           VARCHAR(20)     NOT NULL DEFAULT 'draft',
    `sort_order`       INT             NOT NULL DEFAULT 0,
    `is_demo`          TINYINT(1)      NOT NULL DEFAULT 0,
    `created_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`       TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_ta_slug` (`slug`),
    KEY `idx_ta_status` (`status`),
    KEY `idx_ta_sort` (`sort_order`),
    CONSTRAINT `fk_ta_image` FOREIGN KEY (`image_id`) REFERENCES `media` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- Products ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `products` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`             VARCHAR(200)    NOT NULL,
    `code`             VARCHAR(80)     NULL DEFAULT NULL,
    `slug`             VARCHAR(220)    NOT NULL,
    `short_description` VARCHAR(500)   NULL DEFAULT NULL,
    `description`      LONGTEXT        NULL DEFAULT NULL,       -- sanitised rich text
    `status`           VARCHAR(20)     NOT NULL DEFAULT 'draft',
    `is_featured`      TINYINT(1)      NOT NULL DEFAULT 0,
    `is_demo`          TINYINT(1)      NOT NULL DEFAULT 0,
    `sort_order`       INT             NOT NULL DEFAULT 0,
    -- scientific / product info (all OPTIONAL — owner supplies real data)
    `generic_name`     VARCHAR(255)    NULL DEFAULT NULL,
    `composition`      TEXT            NULL DEFAULT NULL,
    `strength`         VARCHAR(120)    NULL DEFAULT NULL,
    `dosage_form_id`   BIGINT UNSIGNED NULL DEFAULT NULL,
    `pack_size`        VARCHAR(120)    NULL DEFAULT NULL,
    -- catalog
    `category_id`      BIGINT UNSIGNED NULL DEFAULT NULL,
    `hero_image_id`    BIGINT UNSIGNED NULL DEFAULT NULL,
    -- SEO
    `meta_title`       VARCHAR(255)    NULL DEFAULT NULL,
    `meta_description` VARCHAR(320)    NULL DEFAULT NULL,
    `canonical_url`    VARCHAR(255)    NULL DEFAULT NULL,
    `og_image_id`      BIGINT UNSIGNED NULL DEFAULT NULL,
    `robots`           VARCHAR(60)     NULL DEFAULT NULL,
    -- audit
    `created_by`       BIGINT UNSIGNED NULL DEFAULT NULL,
    `updated_by`       BIGINT UNSIGNED NULL DEFAULT NULL,
    `published_at`     TIMESTAMP       NULL DEFAULT NULL,
    `created_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`       TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_products_slug` (`slug`),
    KEY `idx_products_code` (`code`),
    KEY `idx_products_status` (`status`),
    KEY `idx_products_featured` (`is_featured`),
    KEY `idx_products_category` (`category_id`),
    KEY `idx_products_dosage` (`dosage_form_id`),
    KEY `idx_products_sort` (`sort_order`),
    KEY `idx_products_deleted` (`deleted_at`),
    KEY `idx_products_name` (`name`),
    CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`)    REFERENCES `product_categories` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_products_dosage`   FOREIGN KEY (`dosage_form_id`) REFERENCES `dosage_forms` (`id`)       ON DELETE SET NULL,
    CONSTRAINT `fk_products_hero`     FOREIGN KEY (`hero_image_id`)  REFERENCES `media` (`id`)              ON DELETE SET NULL,
    CONSTRAINT `fk_products_og`       FOREIGN KEY (`og_image_id`)    REFERENCES `media` (`id`)              ON DELETE SET NULL,
    CONSTRAINT `fk_products_creator`  FOREIGN KEY (`created_by`)     REFERENCES `users` (`id`)              ON DELETE SET NULL,
    CONSTRAINT `fk_products_updater`  FOREIGN KEY (`updated_by`)     REFERENCES `users` (`id`)              ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- Product ↔ Therapeutic areas (M:N) --------------------------------------
CREATE TABLE IF NOT EXISTS `product_therapeutic_areas` (
    `product_id`          BIGINT UNSIGNED NOT NULL,
    `therapeutic_area_id` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`product_id`, `therapeutic_area_id`),
    KEY `idx_pta_ta` (`therapeutic_area_id`),
    CONSTRAINT `fk_pta_product` FOREIGN KEY (`product_id`)          REFERENCES `products` (`id`)          ON DELETE CASCADE,
    CONSTRAINT `fk_pta_ta`      FOREIGN KEY (`therapeutic_area_id`) REFERENCES `therapeutic_areas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- Product images (gallery; file lives in media) --------------------------
CREATE TABLE IF NOT EXISTS `product_images` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `media_id`   BIGINT UNSIGNED NOT NULL,
    `alt_text`   VARCHAR(255)    NULL DEFAULT NULL,
    `is_primary` TINYINT(1)      NOT NULL DEFAULT 0,
    `sort_order` INT             NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pimg_product` (`product_id`, `sort_order`),
    CONSTRAINT `fk_pimg_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pimg_media`   FOREIGN KEY (`media_id`)   REFERENCES `media` (`id`)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- Product documents (PDF only; file lives in media) ----------------------
CREATE TABLE IF NOT EXISTS `product_documents` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id`   BIGINT UNSIGNED NOT NULL,
    `media_id`     BIGINT UNSIGNED NOT NULL,
    `display_name` VARCHAR(200)    NOT NULL,
    `doc_type`     VARCHAR(40)     NOT NULL DEFAULT 'document', -- spec_sheet|brochure|technical|document
    `uploaded_by`  BIGINT UNSIGNED NULL DEFAULT NULL,
    `sort_order`   INT             NOT NULL DEFAULT 0,
    `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pdoc_product` (`product_id`, `sort_order`),
    CONSTRAINT `fk_pdoc_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pdoc_media`   FOREIGN KEY (`media_id`)   REFERENCES `media` (`id`)    ON DELETE CASCADE,
    CONSTRAINT `fk_pdoc_user`    FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- Product specifications (structured key/value/unit) ---------------------
CREATE TABLE IF NOT EXISTS `product_specifications` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `title`      VARCHAR(150)    NOT NULL,
    `value`      VARCHAR(255)    NOT NULL,
    `unit`       VARCHAR(40)     NULL DEFAULT NULL,
    `sort_order` INT             NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_pspec_product` (`product_id`, `sort_order`),
    CONSTRAINT `fk_pspec_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- Link leads to a product (product enquiries) ----------------------------
ALTER TABLE `leads` ADD COLUMN `product_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `business_type`;
ALTER TABLE `leads` ADD KEY `idx_leads_product` (`product_id`);
ALTER TABLE `leads` ADD CONSTRAINT `fk_leads_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL;
