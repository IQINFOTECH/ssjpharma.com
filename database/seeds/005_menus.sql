-- Menus + items (idempotent). Navigation is data-driven; templates never hardcode
-- links. Items reference pages by slug so they resolve to the right URL.

INSERT IGNORE INTO `menus` (`key`,`name`) VALUES
  ('header', 'Header Menu'),
  ('mobile', 'Mobile Menu'),
  ('footer', 'Footer Menu');

-- ---- Header + Mobile (same starter items) ----------------------------------
-- Home
INSERT INTO `menu_items` (`menu_id`,`label`,`page_id`,`sort_order`)
SELECT m.id, 'Home', (SELECT id FROM `pages` WHERE `slug`='home'), 10
FROM `menus` m WHERE m.`key` IN ('header','mobile')
AND NOT EXISTS (SELECT 1 FROM `menu_items` mi WHERE mi.menu_id=m.id AND mi.label='Home');

INSERT INTO `menu_items` (`menu_id`,`label`,`page_id`,`sort_order`)
SELECT m.id, 'About Us', (SELECT id FROM `pages` WHERE `slug`='about-us'), 20
FROM `menus` m WHERE m.`key` IN ('header','mobile')
AND NOT EXISTS (SELECT 1 FROM `menu_items` mi WHERE mi.menu_id=m.id AND mi.label='About Us');

INSERT INTO `menu_items` (`menu_id`,`label`,`page_id`,`sort_order`)
SELECT m.id, 'Quality', (SELECT id FROM `pages` WHERE `slug`='quality'), 30
FROM `menus` m WHERE m.`key` IN ('header','mobile')
AND NOT EXISTS (SELECT 1 FROM `menu_items` mi WHERE mi.menu_id=m.id AND mi.label='Quality');

INSERT INTO `menu_items` (`menu_id`,`label`,`page_id`,`sort_order`)
SELECT m.id, 'Become a Distributor', (SELECT id FROM `pages` WHERE `slug`='become-a-distributor'), 40
FROM `menus` m WHERE m.`key` IN ('header','mobile')
AND NOT EXISTS (SELECT 1 FROM `menu_items` mi WHERE mi.menu_id=m.id AND mi.label='Become a Distributor');

INSERT INTO `menu_items` (`menu_id`,`label`,`page_id`,`sort_order`)
SELECT m.id, 'Contact Us', (SELECT id FROM `pages` WHERE `slug`='contact-us'), 50
FROM `menus` m WHERE m.`key` IN ('header','mobile')
AND NOT EXISTS (SELECT 1 FROM `menu_items` mi WHERE mi.menu_id=m.id AND mi.label='Contact Us');

-- ---- Footer columns (nested) -----------------------------------------------
-- Column headings (no link)
INSERT INTO `menu_items` (`menu_id`,`label`,`url`,`sort_order`)
SELECT m.id, 'Company', NULL, 10
FROM `menus` m WHERE m.`key`='footer'
AND NOT EXISTS (SELECT 1 FROM `menu_items` mi WHERE mi.menu_id=m.id AND mi.label='Company');

INSERT INTO `menu_items` (`menu_id`,`label`,`url`,`sort_order`)
SELECT m.id, 'Explore', NULL, 20
FROM `menus` m WHERE m.`key`='footer'
AND NOT EXISTS (SELECT 1 FROM `menu_items` mi WHERE mi.menu_id=m.id AND mi.label='Explore');

-- Company children
INSERT INTO `menu_items` (`menu_id`,`parent_id`,`label`,`page_id`,`sort_order`)
SELECT m.id, (SELECT id FROM `menu_items` WHERE menu_id=m.id AND label='Company'),
       'About Us', (SELECT id FROM `pages` WHERE `slug`='about-us'), 10
FROM `menus` m WHERE m.`key`='footer'
AND NOT EXISTS (SELECT 1 FROM `menu_items` mi WHERE mi.menu_id=m.id AND mi.label='About Us');

INSERT INTO `menu_items` (`menu_id`,`parent_id`,`label`,`page_id`,`sort_order`)
SELECT m.id, (SELECT id FROM `menu_items` WHERE menu_id=m.id AND label='Company'),
       'Quality', (SELECT id FROM `pages` WHERE `slug`='quality'), 20
FROM `menus` m WHERE m.`key`='footer'
AND NOT EXISTS (SELECT 1 FROM `menu_items` mi WHERE mi.menu_id=m.id AND mi.label='Quality');

-- Explore children
INSERT INTO `menu_items` (`menu_id`,`parent_id`,`label`,`page_id`,`sort_order`)
SELECT m.id, (SELECT id FROM `menu_items` WHERE menu_id=m.id AND label='Explore'),
       'Become a Distributor', (SELECT id FROM `pages` WHERE `slug`='become-a-distributor'), 10
FROM `menus` m WHERE m.`key`='footer'
AND NOT EXISTS (SELECT 1 FROM `menu_items` mi WHERE mi.menu_id=m.id AND mi.label='Become a Distributor');

INSERT INTO `menu_items` (`menu_id`,`parent_id`,`label`,`page_id`,`sort_order`)
SELECT m.id, (SELECT id FROM `menu_items` WHERE menu_id=m.id AND label='Explore'),
       'Contact Us', (SELECT id FROM `pages` WHERE `slug`='contact-us'), 20
FROM `menus` m WHERE m.`key`='footer'
AND NOT EXISTS (SELECT 1 FROM `menu_items` mi WHERE mi.menu_id=m.id AND mi.label='Contact Us');
