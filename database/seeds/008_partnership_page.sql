-- Partnership enquiry page (idempotent). Uses the reusable 'contact' template so
-- the shared enquiry form is injected; form_key 'partnership' → partnership
-- enquiry type/source (resolved server-side). Placeholder copy only — the owner
-- controls the real content and does not imply any specific partnership offer.
INSERT IGNORE INTO `pages` (`title`,`slug`,`status`,`template`,`is_home`,`meta_title`,`meta_description`,`robots`,`published_at`) VALUES
  ('Partnership', 'partnership', 'published', 'contact', 0, 'Partnership', '', 'index,follow', NOW());

INSERT IGNORE INTO `page_sections` (`page_id`,`type`,`sort_order`,`data`)
SELECT id, 'hero', 10, '{"style":"premium","size":"small","heading":"Partner","heading_highlight":"With Us","subheading":"Tell us about your organisation and we will be in touch.","image_id":"/assets/hero-partner.svg","image_alt":"Partnership and distribution","align":"left"}'
FROM `pages` WHERE `slug`='partnership' AND NOT EXISTS (SELECT 1 FROM `page_sections` s WHERE s.page_id = pages.id);
