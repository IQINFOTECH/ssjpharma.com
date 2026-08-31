-- Phase 4 lead extras (idempotent). Adds the Spam status, more sources, and
-- lead/auto-reply settings. No business claims or response-time promises.

-- Spam status (terminal; not won/lost).
INSERT IGNORE INTO `lead_statuses` (`key`,`name`,`color`,`sort_order`,`is_default`,`is_won`,`is_lost`) VALUES
  ('spam', 'Spam', '#64748b', 70, 0, 0, 0);

-- Extended sources (attribution). Existing rows are preserved.
INSERT IGNORE INTO `lead_sources` (`key`,`name`) VALUES
  ('distributor_enquiry', 'Distributor Enquiry'),
  ('partnership_enquiry', 'Partnership Enquiry'),
  ('whatsapp',            'WhatsApp'),
  ('website_cta',         'Website CTA'),
  ('organic_search',      'Organic Search'),
  ('paid_search',         'Paid Search'),
  ('social',              'Social'),
  ('direct',              'Direct');

-- Lead + auto-reply settings (auto-reply OFF by default; safe generic copy).
INSERT IGNORE INTO `settings` (`key`,`value`,`type`,`group`,`label`,`sort_order`) VALUES
  ('lead_autoreply_enabled', '0', 'bool',   'lead', 'Send auto-reply to enquirer', 50),
  ('lead_autoreply_subject', 'We have received your enquiry', 'string', 'lead', 'Auto-reply subject', 60),
  ('lead_autoreply_message',
   'Thank you for contacting SSJ Pharmaceuticals. Your enquiry has been received and our team will review it. This is an automated acknowledgement.',
   'text', 'lead', 'Auto-reply message', 70),
  ('privacy_policy_version', '1.0', 'string', 'lead', 'Privacy policy version (recorded with consent)', 80);
