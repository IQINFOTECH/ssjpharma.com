-- Phase 1: CMS-managed redirects. `from_path` is a site-relative path (unique).
-- `to_url` may be internal (/x) or a vetted external URL. Loop/self checks are
-- enforced in the RedirectService (SECURITY_PLAN — open-redirect protection).
CREATE TABLE IF NOT EXISTS `redirects` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `from_path`  VARCHAR(255)    NOT NULL,
    `to_url`     VARCHAR(500)    NOT NULL,
    `code`       SMALLINT        NOT NULL DEFAULT 301,   -- 301|302
    `is_active`  TINYINT(1)      NOT NULL DEFAULT 1,
    `hits`       BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `created_by` BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_redirects_from` (`from_path`),
    KEY `idx_redirects_active` (`is_active`),
    CONSTRAINT `fk_redirects_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
