-- Starter pages + sections (idempotent). PLACEHOLDER copy only — no invented
-- pharmaceutical facts, statistics, certifications, testimonials or claims. The
-- admin replaces this content in the CMS.

INSERT IGNORE INTO `pages` (`title`,`slug`,`status`,`template`,`is_home`,`meta_title`,`meta_description`,`robots`,`published_at`) VALUES
  ('Home',                  'home',                  'published', 'default', 1, 'SSJ Pharmaceuticals', '', 'index,follow', NOW()),
  ('About Us',              'about-us',              'published', 'default', 0, 'About Us',            '', 'index,follow', NOW()),
  ('Quality',               'quality',               'published', 'default', 0, 'Quality',             '', 'index,follow', NOW()),
  ('Contact Us',            'contact-us',            'published', 'contact', 0, 'Contact Us',          '', 'index,follow', NOW()),
  ('Become a Distributor',  'become-a-distributor',  'published', 'contact', 0, 'Become a Distributor','', 'index,follow', NOW()),
  ('Thank You',             'thank-you',             'published', 'default', 0, 'Thank You',           '', 'noindex,follow', NOW());

-- Home sections
INSERT IGNORE INTO `page_sections` (`page_id`,`type`,`sort_order`,`data`)
SELECT id, 'hero', 10, '{"eyebrow":"","heading":"SSJ Pharmaceuticals","subheading":"This is placeholder content. Edit it in the CMS.","primary_label":"Enquire Now","primary_url":"/contact-us","secondary_label":"","secondary_url":"","image_id":null,"align":"left"}'
FROM `pages` WHERE `slug`='home' AND NOT EXISTS (SELECT 1 FROM `page_sections` s WHERE s.page_id = pages.id AND s.type='hero');

INSERT IGNORE INTO `page_sections` (`page_id`,`type`,`sort_order`,`data`)
SELECT id, 'cards', 20, '{"heading":"Placeholder heading","subheading":"","cards":[{"title":"Card one","text":"Placeholder text.","icon":"","url":""},{"title":"Card two","text":"Placeholder text.","icon":"","url":""},{"title":"Card three","text":"Placeholder text.","icon":"","url":""}]}'
FROM `pages` WHERE `slug`='home' AND NOT EXISTS (SELECT 1 FROM `page_sections` s WHERE s.page_id = pages.id AND s.type='cards');

INSERT IGNORE INTO `page_sections` (`page_id`,`type`,`sort_order`,`data`)
SELECT id, 'contact_cta', 30, '{"heading":"Get in touch","text":"Placeholder text. Edit in the CMS.","button_label":"Enquire Now","button_url":"/contact-us"}'
FROM `pages` WHERE `slug`='home' AND NOT EXISTS (SELECT 1 FROM `page_sections` s WHERE s.page_id = pages.id AND s.type='contact_cta');

-- About / Quality: hero + richtext
INSERT IGNORE INTO `page_sections` (`page_id`,`type`,`sort_order`,`data`)
SELECT id, 'hero', 10, '{"heading":"About Us","subheading":"Placeholder content. Edit in the CMS.","primary_label":"","primary_url":"","align":"left"}'
FROM `pages` WHERE `slug`='about-us' AND NOT EXISTS (SELECT 1 FROM `page_sections` s WHERE s.page_id = pages.id);

INSERT IGNORE INTO `page_sections` (`page_id`,`type`,`sort_order`,`data`)
SELECT id, 'richtext', 20, '{"heading":"","body":"<p>Placeholder content. Edit this in the CMS.</p>"}'
FROM `pages` WHERE `slug`='about-us' AND (SELECT COUNT(*) FROM `page_sections` s WHERE s.page_id = pages.id) < 2;

INSERT IGNORE INTO `page_sections` (`page_id`,`type`,`sort_order`,`data`)
SELECT id, 'hero', 10, '{"heading":"Quality","subheading":"Placeholder content. Edit in the CMS.","primary_label":"","primary_url":"","align":"left"}'
FROM `pages` WHERE `slug`='quality' AND NOT EXISTS (SELECT 1 FROM `page_sections` s WHERE s.page_id = pages.id);

INSERT IGNORE INTO `page_sections` (`page_id`,`type`,`sort_order`,`data`)
SELECT id, 'richtext', 20, '{"heading":"","body":"<p>Placeholder content. Edit this in the CMS.</p>"}'
FROM `pages` WHERE `slug`='quality' AND (SELECT COUNT(*) FROM `page_sections` s WHERE s.page_id = pages.id) < 2;

-- Contact / Distributor: small hero above the injected form
INSERT IGNORE INTO `page_sections` (`page_id`,`type`,`sort_order`,`data`)
SELECT id, 'hero', 10, '{"heading":"Contact Us","subheading":"Send us an enquiry and we will get back to you.","primary_label":"","primary_url":"","align":"center","size":"small"}'
FROM `pages` WHERE `slug`='contact-us' AND NOT EXISTS (SELECT 1 FROM `page_sections` s WHERE s.page_id = pages.id);

INSERT IGNORE INTO `page_sections` (`page_id`,`type`,`sort_order`,`data`)
SELECT id, 'hero', 10, '{"heading":"Become a Distributor","subheading":"Tell us about your business and we will be in touch.","primary_label":"","primary_url":"","align":"center","size":"small"}'
FROM `pages` WHERE `slug`='become-a-distributor' AND NOT EXISTS (SELECT 1 FROM `page_sections` s WHERE s.page_id = pages.id);

-- Thank you
INSERT IGNORE INTO `page_sections` (`page_id`,`type`,`sort_order`,`data`)
SELECT id, 'hero', 10, '{"heading":"Thank you","subheading":"Thank you for contacting SSJ Pharmaceuticals.","primary_label":"Back to home","primary_url":"/","align":"center"}'
FROM `pages` WHERE `slug`='thank-you' AND NOT EXISTS (SELECT 1 FROM `page_sections` s WHERE s.page_id = pages.id);
