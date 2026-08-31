-- Phase 1: global CMS settings (key/value). Secrets NEVER live here — real SMTP
-- and API credentials stay in .env (ADR-001). `group` organises the admin UI.
CREATE TABLE IF NOT EXISTS `settings` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `key`        VARCHAR(120)    NOT NULL,
    `value`      TEXT            NULL DEFAULT NULL,
    `type`       VARCHAR(20)     NOT NULL DEFAULT 'string', -- string|text|bool|int|json|media|email|url
    `group`      VARCHAR(40)     NOT NULL DEFAULT 'general',
    `label`      VARCHAR(150)    NULL DEFAULT NULL,
    `sort_order` INT             NOT NULL DEFAULT 0,
    `updated_by` BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_settings_key` (`key`),
    KEY `idx_settings_group` (`group`),
    CONSTRAINT `fk_settings_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
