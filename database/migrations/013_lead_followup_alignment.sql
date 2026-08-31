-- Phase 4 (foundation completion): add the follow-up date and align the priority
-- vocabulary to Low/Medium/High/Urgent. Non-destructive; portable (MySQL/MariaDB).

-- Follow-up date (a scheduling day, not a promise). Indexed for "due" queries/sort.
ALTER TABLE `leads`
  ADD COLUMN `follow_up_date` DATE NULL DEFAULT NULL AFTER `last_contacted_at`;
ALTER TABLE `leads`
  ADD KEY `idx_leads_followup` (`follow_up_date`);

-- Priority vocabulary: the middle tier is "medium" (was "normal").
ALTER TABLE `leads`
  MODIFY COLUMN `priority` VARCHAR(20) NOT NULL DEFAULT 'medium';
UPDATE `leads` SET `priority` = 'medium' WHERE `priority` = 'normal';
