-- Starter pages + sections (idempotent). PLACEHOLDER copy only — no invented
-- pharmaceutical facts, statistics, certifications, testimonials or claims. The
-- admin replaces this content in the CMS.

INSERT IGNORE INTO `pages` (`title`,`slug`,`status`,`template`,`is_home`,`meta_title`,`meta_description`,`robots`,`published_at`) VALUES
  ('Home',                  'home',                  'published', 'default', 1, 'SSJ Pharmaceuticals LLP — Pharmaceutical Manufacturing', 'SSJ Pharmaceuticals LLP is a pharmaceutical manufacturing company offering contract manufacturing, bulk drug formulation and customized production for businesses and healthcare partners.', 'index,follow', NOW()),
  ('About Us',              'about-us',              'published', 'default', 0, 'About Us',            '', 'index,follow', NOW()),
  ('Quality',               'quality',               'published', 'default', 0, 'Quality',             '', 'index,follow', NOW()),
  ('Contact Us',            'contact-us',            'published', 'contact', 0, 'Contact Us',          '', 'index,follow', NOW()),
  ('Become a Distributor',  'become-a-distributor',  'published', 'contact', 0, 'Become a Distributor','', 'index,follow', NOW()),
  ('Thank You',             'thank-you',             'published', 'default', 0, 'Thank You',           '', 'noindex,follow', NOW());

-- Home sections
INSERT IGNORE INTO `page_sections` (`page_id`,`type`,`sort_order`,`data`)
SELECT id, 'hero', 10, '{"style":"premium","eyebrow":"Trusted by healthcare. Driven by quality.","heading":"Quality today,","heading_highlight":"healthier tomorrow","subheading":"Delivering high-quality pharmaceutical products with integrity, innovation and care.","primary_label":"Explore our products","primary_url":"/products","secondary_label":"Send an enquiry","secondary_url":"/contact-us","image_id":null,"image_alt":"Pharmaceutical laboratory","features":[{"label":"Quality Assured"},{"label":"Wide Range of Products"},{"label":"Trusted Partner"},{"label":"Pan India Presence"}],"badge_text":"Safe • Effective • Reliable","align":"left"}'
FROM `pages` WHERE `slug`='home' AND NOT EXISTS (SELECT 1 FROM `page_sections` s WHERE s.page_id = pages.id AND s.type='hero');

INSERT IGNORE INTO `page_sections` (`page_id`,`type`,`sort_order`,`data`)
SELECT id, 'cards', 20, '{"heading":"Our manufacturing solutions","subheading":"Pharmaceutical manufacturing services for businesses and healthcare partners.","cards":[{"title":"Contract Manufacturing","text":"Third-party and contract manufacturing for businesses and healthcare partners.","icon":"","url":"/partnership"},{"title":"Bulk Drug Formulation","text":"Bulk drug formulation under defined processes and quality controls.","icon":"","url":"/partnership"},{"title":"Custom Production","text":"Customized pharmaceutical production tailored to partner requirements.","icon":"","url":"/partnership"}]}'
FROM `pages` WHERE `slug`='home' AND NOT EXISTS (SELECT 1 FROM `page_sections` s WHERE s.page_id = pages.id AND s.type='cards');

INSERT IGNORE INTO `page_sections` (`page_id`,`type`,`sort_order`,`data`)
SELECT id, 'faq', 30, '{"eyebrow":"Common questions","heading":"Answers, up front","items":[{"question":"What does SSJ Pharmaceuticals do?","answer":"SSJ Pharmaceuticals LLP is a pharmaceutical manufacturing company focused on delivering high-quality, safe and reliable healthcare products for businesses and healthcare partners."},{"question":"What manufacturing services do you offer?","answer":"We offer pharmaceutical manufacturing solutions including contract manufacturing, bulk drug formulation, and customized pharmaceutical production."},{"question":"How can I partner with SSJ Pharmaceuticals?","answer":"Share your requirement through our distributor or partnership enquiry form and our team will get back to you."},{"question":"How do I get in touch?","answer":"You can reach us by phone or email, or start a WhatsApp chat using the button on the site."}]}'
FROM `pages` WHERE `slug`='home' AND NOT EXISTS (SELECT 1 FROM `page_sections` s WHERE s.page_id = pages.id AND s.type='faq');

INSERT IGNORE INTO `page_sections` (`page_id`,`type`,`sort_order`,`data`)
SELECT id, 'contact_cta', 40, '{"heading":"Discuss your requirement","text":"Tell us about your distribution, manufacturing or partnership needs and we will get back to you.","button_label":"Send an enquiry","button_url":"/contact-us"}'
FROM `pages` WHERE `slug`='home' AND NOT EXISTS (SELECT 1 FROM `page_sections` s WHERE s.page_id = pages.id AND s.type='contact_cta');

-- About / Quality: hero + richtext
INSERT IGNORE INTO `page_sections` (`page_id`,`type`,`sort_order`,`data`)
SELECT id, 'hero', 10, '{"heading":"About Us","subheading":"Placeholder content. Edit in the CMS.","primary_label":"","primary_url":"","image_id":"/assets/sample-lab.svg","align":"left"}'
FROM `pages` WHERE `slug`='about-us' AND NOT EXISTS (SELECT 1 FROM `page_sections` s WHERE s.page_id = pages.id);

INSERT IGNORE INTO `page_sections` (`page_id`,`type`,`sort_order`,`data`)
SELECT id, 'richtext', 20, '{"heading":"","body":"<p>Placeholder content. Edit this in the CMS.</p>"}'
FROM `pages` WHERE `slug`='about-us' AND (SELECT COUNT(*) FROM `page_sections` s WHERE s.page_id = pages.id) < 2;

INSERT IGNORE INTO `page_sections` (`page_id`,`type`,`sort_order`,`data`)
SELECT id, 'hero', 10, '{"heading":"Quality","subheading":"Placeholder content. Edit in the CMS.","primary_label":"","primary_url":"","image_id":"/assets/sample-quality.svg","align":"left"}'
FROM `pages` WHERE `slug`='quality' AND NOT EXISTS (SELECT 1 FROM `page_sections` s WHERE s.page_id = pages.id);

INSERT IGNORE INTO `page_sections` (`page_id`,`type`,`sort_order`,`data`)
SELECT id, 'richtext', 20, '{"heading":"","body":"<p>Placeholder content. Edit this in the CMS.</p>"}'
FROM `pages` WHERE `slug`='quality' AND (SELECT COUNT(*) FROM `page_sections` s WHERE s.page_id = pages.id) < 2;

-- Contact / Distributor: small hero above the injected form
INSERT IGNORE INTO `page_sections` (`page_id`,`type`,`sort_order`,`data`)
SELECT id, 'hero', 10, '{"heading":"Contact Us","subheading":"Send us an enquiry and we will get back to you.","primary_label":"","primary_url":"","align":"center","size":"small"}'
FROM `pages` WHERE `slug`='contact-us' AND NOT EXISTS (SELECT 1 FROM `page_sections` s WHERE s.page_id = pages.id);

INSERT IGNORE INTO `page_sections` (`page_id`,`type`,`sort_order`,`data`)
SELECT id, 'hero', 10, '{"heading":"Become a Distributor","subheading":"Tell us about your business and we will be in touch.","primary_label":"","primary_url":"","image_id":"/assets/sample-warehouse.svg","align":"left"}'
FROM `pages` WHERE `slug`='become-a-distributor' AND NOT EXISTS (SELECT 1 FROM `page_sections` s WHERE s.page_id = pages.id);

-- Thank you
INSERT IGNORE INTO `page_sections` (`page_id`,`type`,`sort_order`,`data`)
SELECT id, 'hero', 10, '{"heading":"Thank you","subheading":"Thank you for contacting SSJ Pharmaceuticals.","primary_label":"Back to home","primary_url":"/","align":"center"}'
FROM `pages` WHERE `slug`='thank-you' AND NOT EXISTS (SELECT 1 FROM `page_sections` s WHERE s.page_id = pages.id);
