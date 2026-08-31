-- Phase 1: lead-capture FOUNDATION (the contact form writes here). The full CRM
-- pipeline (assignment, activities, notes UI, dashboards) is a later phase; these
-- tables match DATABASE_PLAN §6 so the CRM extends them without a rewrite.

CREATE TABLE IF NOT EXISTS `lead_statuses` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `key`        VARCHAR(40)     NOT NULL,
    `name`       VARCHAR(80)     NOT NULL,
    `color`      VARCHAR(20)     NULL DEFAULT NULL,
    `sort_order` INT             NOT NULL DEFAULT 0,
    `is_default` TINYINT(1)      NOT NULL DEFAULT 0,
    `is_won`     TINYINT(1)      NOT NULL DEFAULT 0,
    `is_lost`    TINYINT(1)      NOT NULL DEFAULT 0,
    `is_active`  TINYINT(1)      NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_lead_statuses_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lead_sources` (
    `id`        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `key`       VARCHAR(40)     NOT NULL,
    `name`      VARCHAR(80)     NOT NULL,
    `is_active` TINYINT(1)      NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_lead_sources_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `leads` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `reference`         VARCHAR(30)     NOT NULL,
    `name`              VARCHAR(150)    NOT NULL,
    `company`           VARCHAR(180)    NULL DEFAULT NULL,
    `email`             VARCHAR(190)    NULL DEFAULT NULL,
    `phone`             VARCHAR(40)     NULL DEFAULT NULL,
    `whatsapp`          VARCHAR(40)     NULL DEFAULT NULL,
    `country`           VARCHAR(100)    NULL DEFAULT NULL,
    `city`              VARCHAR(100)    NULL DEFAULT NULL,
    `business_type`     VARCHAR(100)    NULL DEFAULT NULL,
    `message`           TEXT            NULL DEFAULT NULL,
    `preferred_contact` VARCHAR(20)     NULL DEFAULT NULL,   -- email|phone|whatsapp
    `priority`          VARCHAR(20)     NOT NULL DEFAULT 'normal',
    `consent`           TINYINT(1)      NOT NULL DEFAULT 0,
    `source_id`         BIGINT UNSIGNED NULL DEFAULT NULL,
    `status_id`         BIGINT UNSIGNED NULL DEFAULT NULL,
    `landing_page`      VARCHAR(255)    NULL DEFAULT NULL,
    `referrer`          VARCHAR(255)    NULL DEFAULT NULL,
    `utm_source`        VARCHAR(120)    NULL DEFAULT NULL,
    `utm_medium`        VARCHAR(120)    NULL DEFAULT NULL,
    `utm_campaign`      VARCHAR(120)    NULL DEFAULT NULL,
    `utm_term`          VARCHAR(120)    NULL DEFAULT NULL,
    `utm_content`       VARCHAR(120)    NULL DEFAULT NULL,
    `ip`                VARCHAR(45)     NULL DEFAULT NULL,
    `user_agent`        VARCHAR(255)    NULL DEFAULT NULL,
    `is_spam`           TINYINT(1)      NOT NULL DEFAULT 0,
    `notified_at`       TIMESTAMP       NULL DEFAULT NULL,
    `created_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`        TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_leads_reference` (`reference`),
    KEY `idx_leads_status` (`status_id`),
    KEY `idx_leads_source` (`source_id`),
    KEY `idx_leads_created` (`created_at`),
    KEY `idx_leads_email` (`email`),
    KEY `idx_leads_deleted` (`deleted_at`),
    CONSTRAINT `fk_leads_status` FOREIGN KEY (`status_id`) REFERENCES `lead_statuses` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_leads_source` FOREIGN KEY (`source_id`) REFERENCES `lead_sources` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `contact_submissions` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lead_id`    BIGINT UNSIGNED NULL DEFAULT NULL,
    `form_key`   VARCHAR(60)     NOT NULL DEFAULT 'contact',
    `payload`    JSON            NULL,
    `ip`         VARCHAR(45)     NULL DEFAULT NULL,
    `user_agent` VARCHAR(255)    NULL DEFAULT NULL,
    `is_spam`    TINYINT(1)      NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_submissions_lead` (`lead_id`),
    KEY `idx_submissions_created` (`created_at`),
    CONSTRAINT `fk_submissions_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
