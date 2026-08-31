-- Dosage-form OPTIONS for the CMS (idempotent). These are structural picklist
-- values only; they do NOT imply SSJ Pharmaceuticals manufactures any of them.
-- The admin can add/edit/deactivate them. NO products are seeded (owner supplies
-- real product data; demo products are created on-demand with is_demo=1).
INSERT IGNORE INTO `dosage_forms` (`name`,`slug`,`is_active`,`sort_order`) VALUES
  ('Tablet',     'tablet',     1, 10),
  ('Capsule',    'capsule',    1, 20),
  ('Syrup',      'syrup',      1, 30),
  ('Suspension', 'suspension', 1, 40),
  ('Injection',  'injection',  1, 50),
  ('Cream',      'cream',      1, 60),
  ('Ointment',   'ointment',   1, 70),
  ('Drops',      'drops',      1, 80),
  ('Gel',        'gel',        1, 90),
  ('Sachet',     'sachet',     1, 100);
