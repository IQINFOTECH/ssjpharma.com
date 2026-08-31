-- Phase 2: extend the Phase 1 auth/RBAC foundation. SCHEMA ONLY (data catalogue
-- + role matrix live in the idempotent seed 001_rbac.sql). Non-destructive:
-- adds columns/tables, never drops existing data.

-- --- users: profile fields + lockout support --------------------------------
-- Applied exactly once by bin/migrate.php (portable across MySQL & MariaDB —
-- no engine-specific IF NOT EXISTS on ALTER).
ALTER TABLE `users`
  ADD COLUMN `username`      VARCHAR(60)  NULL DEFAULT NULL AFTER `email`,
  ADD COLUMN `phone`         VARCHAR(40)  NULL DEFAULT NULL AFTER `username`,
  ADD COLUMN `locked_until`  TIMESTAMP    NULL DEFAULT NULL AFTER `last_login_ip`;

-- Unique username when present (NULLs allowed to repeat).
ALTER TABLE `users` ADD UNIQUE KEY `uq_users_username` (`username`);

-- --- roles: activate/deactivate ---------------------------------------------
ALTER TABLE `roles`
  ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `is_system`;

-- --- password resets (token stored HASHED, single-use, expiring) -------------
CREATE TABLE IF NOT EXISTS `password_resets` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    BIGINT UNSIGNED NOT NULL,
    `token_hash` CHAR(64)        NOT NULL,               -- sha256 of the raw token
    `expires_at` TIMESTAMP       NOT NULL,
    `used_at`    TIMESTAMP       NULL DEFAULT NULL,
    `ip`         VARCHAR(45)     NULL DEFAULT NULL,
    `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_pr_token` (`token_hash`),
    KEY `idx_pr_user` (`user_id`),
    KEY `idx_pr_expires` (`expires_at`),
    CONSTRAINT `fk_pr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- login attempts (throttling; never stores passwords) ---------------------
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email`      VARCHAR(190)    NOT NULL,
    `ip`         VARCHAR(45)     NOT NULL,
    `success`    TINYINT(1)      NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_la_email_time` (`email`, `created_at`),
    KEY `idx_la_ip_time` (`ip`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- audit log (immutable to app users; append-only) -------------------------
CREATE TABLE IF NOT EXISTS `audit_log` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     BIGINT UNSIGNED NULL DEFAULT NULL,     -- actor (NULL for anonymous/system)
    `event`       VARCHAR(60)     NOT NULL,              -- e.g. LOGIN_SUCCESS, USER_CREATED
    `entity_type` VARCHAR(60)     NULL DEFAULT NULL,
    `entity_id`   BIGINT UNSIGNED NULL DEFAULT NULL,
    `ip`          VARCHAR(45)     NULL DEFAULT NULL,
    `user_agent`  VARCHAR(255)    NULL DEFAULT NULL,
    `meta`        JSON            NULL,                  -- NEVER secrets/passwords/tokens
    `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_audit_user` (`user_id`),
    KEY `idx_audit_event` (`event`),
    KEY `idx_audit_entity` (`entity_type`, `entity_id`),
    KEY `idx_audit_created` (`created_at`),
    CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- session registry (view/revoke active sessions) --------------------------
CREATE TABLE IF NOT EXISTS `user_sessions` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id`       VARCHAR(128)    NOT NULL,
    `user_id`          BIGINT UNSIGNED NOT NULL,
    `ip`               VARCHAR(45)     NULL DEFAULT NULL,
    `user_agent`       VARCHAR(255)    NULL DEFAULT NULL,
    `last_activity_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `revoked_at`       TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_sessions_sid` (`session_id`),
    KEY `idx_sessions_user` (`user_id`),
    KEY `idx_sessions_revoked` (`revoked_at`),
    CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
