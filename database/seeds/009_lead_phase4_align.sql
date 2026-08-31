-- Phase 4 alignment (idempotent). Add website / landing_page sources; default
-- priority → "medium". (The Won→Converted status rename is a one-time data fix in
-- migration 015 — doing it here is not idempotent because seed 003 re-seeds the
-- canonical 'converted' row.) Safe to re-run.

-- First-party attribution sources for website + future landing pages.
INSERT IGNORE INTO `lead_sources` (`key`,`name`) VALUES
  ('website',      'Website'),
  ('landing_page', 'Landing Page');

-- Default priority label alignment (only touches the untouched default).
UPDATE `settings` SET `value` = 'medium'
  WHERE `key` = 'lead_default_priority' AND `value` = 'normal';
