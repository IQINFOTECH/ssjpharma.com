-- Phase 5: communications & follow-up infrastructure (schema only; data in seeds).
-- Non-destructive, portable (MySQL 5.7+/MariaDB 10.3+). No SKIP LOCKED dependency
-- (the worker claims rows with an atomic UPDATE for shared-hosting portability).

-- --- Outbound email queue (capture-first: leads enqueue, cron sends) ----------
CREATE TABLE IF NOT EXISTS `email_queue` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lead_id`         BIGINT UNSIGNED NULL DEFAULT NULL,
    `template_key`    VARCHAR(60)     NULL DEFAULT NULL,
    `recipient_email` VARCHAR(190)    NOT NULL,
    `recipient_name`  VARCHAR(150)    NULL DEFAULT NULL,
    `reply_to_email`  VARCHAR(190)    NULL DEFAULT NULL,
    `reply_to_name`   VARCHAR(150)    NULL DEFAULT NULL,
    `subject`         VARCHAR(255)    NOT NULL,
    `body_html`       MEDIUMTEXT      NULL,
    `body_text`       MEDIUMTEXT      NULL,
    `status`          VARCHAR(20)     NOT NULL DEFAULT 'pending', -- pending|processing|sent|failed|cancelled
    `attempts`        INT             NOT NULL DEFAULT 0,
    `max_attempts`    INT             NOT NULL DEFAULT 5,
    `available_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `locked_by`       VARCHAR(64)     NULL DEFAULT NULL,
    `locked_at`       TIMESTAMP       NULL DEFAULT NULL,
    `sent_at`         TIMESTAMP       NULL DEFAULT NULL,
    `last_attempt_at` TIMESTAMP       NULL DEFAULT NULL,
    `last_error`      VARCHAR(255)    NULL DEFAULT NULL,   -- sanitised; NEVER credentials
    `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_emailq_status_avail` (`status`, `available_at`),
    KEY `idx_emailq_created` (`created_at`),
    KEY `idx_emailq_lead` (`lead_id`),
    KEY `idx_emailq_locked` (`locked_by`),
    CONSTRAINT `fk_emailq_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- CMS-managed email templates (rendered at enqueue with safe placeholders) -
CREATE TABLE IF NOT EXISTS `email_templates` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `key`        VARCHAR(60)     NOT NULL,
    `name`       VARCHAR(120)    NOT NULL,
    `subject`    VARCHAR(255)    NOT NULL,
    `body_html`  MEDIUMTEXT      NULL,
    `body_text`  MEDIUMTEXT      NULL,
    `is_active`  TINYINT(1)      NOT NULL DEFAULT 1,
    `updated_by` BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_email_templates_key` (`key`),
    CONSTRAINT `fk_email_tpl_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- CMS-managed WhatsApp message templates (build wa.me URLs ONLY) -----------
CREATE TABLE IF NOT EXISTS `whatsapp_templates` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `key`        VARCHAR(60)     NOT NULL,
    `name`       VARCHAR(120)    NOT NULL,
    `message`    TEXT            NOT NULL,
    `is_active`  TINYINT(1)      NOT NULL DEFAULT 1,
    `updated_by` BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_wa_templates_key` (`key`),
    CONSTRAINT `fk_wa_tpl_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- Follow-up digest idempotency (one digest per assignee per day) -----------
CREATE TABLE IF NOT EXISTS `communication_digests` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     BIGINT UNSIGNED NOT NULL,
    `digest_date` DATE            NOT NULL,
    `lead_count`  INT             NOT NULL DEFAULT 0,
    `status`      VARCHAR(20)     NOT NULL DEFAULT 'queued', -- queued|sent|failed
    `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_digest_user_day` (`user_id`, `digest_date`),
    CONSTRAINT `fk_digest_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
