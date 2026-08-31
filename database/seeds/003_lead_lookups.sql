-- Lead pipeline lookups (idempotent). Statuses/sources for the capture foundation.
INSERT IGNORE INTO `lead_statuses` (`key`,`name`,`color`,`sort_order`,`is_default`,`is_won`,`is_lost`) VALUES
  ('new',        'New',        '#2563eb', 10, 1, 0, 0),
  ('contacted',  'Contacted',  '#7c3aed', 20, 0, 0, 0),
  ('qualified',  'Qualified',  '#0891b2', 30, 0, 0, 0),
  ('proposal',   'Proposal',   '#d97706', 40, 0, 0, 0),
  ('converted',  'Converted',  '#16a34a', 50, 0, 1, 0),
  ('lost',       'Lost',       '#dc2626', 60, 0, 0, 1);

INSERT IGNORE INTO `lead_sources` (`key`,`name`) VALUES
  ('contact_form',   'Contact Form'),
  ('quote_request',  'Distributor / Quote Request'),
  ('product_enquiry','Product Enquiry'),
  ('phone',          'Phone'),
  ('referral',       'Referral'),
  ('other',          'Other');
