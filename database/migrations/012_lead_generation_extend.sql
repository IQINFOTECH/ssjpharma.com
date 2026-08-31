-- Phase 4: extend the lead foundation (SCHEMA only; data in idempotent seeds).
-- Non-destructive: adds columns/tables/indexes, preserves existing lead data.
-- Portable DDL (MySQL & MariaDB) — applied once by bin/migrate.php.

-- --- leads: management, attribution, consent + notification tracking --------
ALTER TABLE `leads`
  ADD COLUMN `state`                 VARCHAR(100)    NULL DEFAULT NULL AFTER `country`,
  ADD COLUMN `enquiry_type`          VARCHAR(40)     NOT NULL DEFAULT 'general' AFTER `business_type`,
  ADD COLUMN `product_name_snapshot` VARCHAR(200)    NULL DEFAULT NULL AFTER `product_id`,
  ADD COLUMN `requirement`           VARCHAR(255)    NULL DEFAULT NULL AFTER `message`,
  ADD COLUMN `source_url`            VARCHAR(255)    NULL DEFAULT NULL AFTER `landing_page`,
  ADD COLUMN `assigned_user_id`      BIGINT UNSIGNED NULL DEFAULT NULL AFTER `status_id`,
  ADD COLUMN `last_contacted_at`     TIMESTAMP       NULL DEFAULT NULL AFTER `assigned_user_id`,
  ADD COLUMN `consent_at`            TIMESTAMP       NULL DEFAULT NULL AFTER `consent`,
  ADD COLUMN `privacy_version`       VARCHAR(20)     NULL DEFAULT NULL AFTER `consent_at`,
  ADD COLUMN `notification_status`   VARCHAR(20)     NOT NULL DEFAULT 'pending' AFTER `notified_at`;

-- Indexes for common admin searches/filters (only where they help).
ALTER TABLE `leads`
  ADD KEY `idx_leads_phone` (`phone`),
  ADD KEY `idx_leads_enquiry` (`enquiry_type`),
  ADD KEY `idx_leads_assigned` (`assigned_user_id`),
  ADD KEY `idx_leads_priority` (`priority`);

ALTER TABLE `leads`
  ADD CONSTRAINT `fk_leads_assigned` FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- --- lead activity timeline (created/status/priority/assign/note/email...) ---
CREATE TABLE IF NOT EXISTS `lead_activities` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lead_id`     BIGINT UNSIGNED NOT NULL,
    `user_id`     BIGINT UNSIGNED NULL DEFAULT NULL,   -- actor (NULL for the public visitor/system)
    `type`        VARCHAR(40)     NOT NULL,            -- created|status_changed|priority_changed|
                                                       -- assigned|unassigned|note|email_sent|email_failed|repeat_enquiry
    `description` TEXT            NULL DEFAULT NULL,    -- note body / human-readable detail
    `meta`        JSON            NULL,                -- structured detail (never secrets)
    `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_lead_act_lead` (`lead_id`, `id`),
    KEY `idx_lead_act_type` (`type`),
    CONSTRAINT `fk_lead_act_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_lead_act_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- WhatsApp CTA click tracking (a click is NOT a lead) ---------------------
CREATE TABLE IF NOT EXISTS `whatsapp_clicks` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `context`      VARCHAR(40)     NOT NULL DEFAULT 'general', -- general|product|contact|...
    `page`         VARCHAR(255)    NULL DEFAULT NULL,
    `product_id`   BIGINT UNSIGNED NULL DEFAULT NULL,
    `utm_source`   VARCHAR(120)    NULL DEFAULT NULL,
    `utm_medium`   VARCHAR(120)    NULL DEFAULT NULL,
    `utm_campaign` VARCHAR(120)    NULL DEFAULT NULL,
    `ip`           VARCHAR(45)     NULL DEFAULT NULL,
    `user_agent`   VARCHAR(255)    NULL DEFAULT NULL,
    `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_waclick_product` (`product_id`),
    KEY `idx_waclick_created` (`created_at`),
    CONSTRAINT `fk_waclick_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
