-- One-time reconciliation of the legacy 'won' lead status → 'converted'
-- (idempotent + safe to run against any prior state). Runs once (tracked).
-- Multi-table UPDATE/DELETE with distinct aliases avoids error 1093.

-- 1) If both exist, move any leads off the legacy 'won' onto 'converted'.
UPDATE `leads` l
  JOIN `lead_statuses` w ON w.`key` = 'won'
  JOIN `lead_statuses` c ON c.`key` = 'converted'
  SET l.`status_id` = c.id
  WHERE l.`status_id` = w.id;

-- 2) Remove the now-unused 'won' row, but ONLY when 'converted' already exists.
DELETE w FROM `lead_statuses` w
  JOIN `lead_statuses` c ON c.`key` = 'converted'
  WHERE w.`key` = 'won';

-- 3) Legacy DBs that only had 'won' (no 'converted' yet): rename it in place.
UPDATE `lead_statuses` SET `key` = 'converted', `name` = 'Converted' WHERE `key` = 'won';
