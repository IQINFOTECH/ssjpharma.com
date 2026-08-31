-- Default CMS settings (idempotent). SAFE PLACEHOLDERS ONLY — no real contact
-- details, claims, or credentials. Only the owner-supplied company/site name and
-- domain are pre-filled; everything else is blank for the admin to complete.

INSERT IGNORE INTO `settings` (`key`,`value`,`type`,`group`,`label`,`sort_order`) VALUES
  -- Company
  ('company_name',        'SSJ Pharmaceuticals LLP', 'string', 'company', 'Company Name',        10),
  ('company_logo',        '',                        'media',  'company', 'Logo',                20),
  ('company_favicon',     '',                        'media',  'company', 'Favicon',             30),
  -- Registered address (owner-provided; editable in Admin → Settings). Granular
  -- fields feed a precise Organization PostalAddress for GEO / local presence.
  ('company_address',     'Sewla Kalan, Shimla Bypass Road, Shiv Lok Colony, Majra', 'text', 'company', 'Address (street)', 40),
  ('company_city',        'Dehradun',                'string', 'company', 'City',                41),
  ('company_state',       'Uttarakhand',             'string', 'company', 'State / Region',      42),
  ('company_postal',      '248171',                  'string', 'company', 'Postal Code',         43),
  ('company_country',     'India',                   'string', 'company', 'Country',             44),
  ('company_phone',       '',                        'string', 'company', 'Phone',               50),
  ('company_email',       '',                        'email',  'company', 'Email',               60),
  ('company_whatsapp',    '',                        'string', 'company', 'WhatsApp',            70),
  ('company_description',  '',                       'text',   'company', 'Business Description', 80),

  -- Website
  ('website_name',        'SSJ Pharmaceuticals',     'string', 'website', 'Website Name',            10),
  ('website_url',         'https://ssjpharma.com',   'url',    'website', 'Website URL',             20),
  ('seo_default_title',   'SSJ Pharmaceuticals',     'string', 'website', 'Default SEO Title',       30),
  ('seo_default_description', '',                    'text',   'website', 'Default Meta Description', 40),
  ('seo_default_og_image', '',                       'media',  'website', 'Default OG Image',        50),

  -- Social
  ('social_linkedin',     '', 'url', 'social', 'LinkedIn',  10),
  ('social_facebook',     '', 'url', 'social', 'Facebook',  20),
  ('social_instagram',    '', 'url', 'social', 'Instagram', 30),
  ('social_youtube',      '', 'url', 'social', 'YouTube',   40),

  -- Lead
  ('lead_sales_email',        '',       'email',  'lead', 'Sales Email',           10),
  ('lead_notification_email', '',       'email',  'lead', 'Notification Email',    20),
  ('lead_default_status',     'new',    'string', 'lead', 'Default Lead Status',   30),
  ('lead_default_priority',   'normal', 'string', 'lead', 'Default Lead Priority', 40),

  -- WhatsApp (click-to-chat only)
  ('whatsapp_number',          '', 'string', 'whatsapp', 'WhatsApp Number', 10),
  ('whatsapp_default_message', 'Hello SSJ Pharmaceuticals, I would like to know more about your products.',
                                   'text',   'whatsapp', 'Default WhatsApp Message', 20),

  -- Analytics (IDs/verification tokens only — never secrets)
  ('analytics_ga_id',            '', 'string', 'analytics', 'Google Analytics 4 ID (G-XXXX)', 10),
  ('analytics_gsc_verification', '', 'string', 'analytics', 'Google Search Console verification token', 20),
  ('analytics_bing_verification','', 'string', 'analytics', 'Bing Webmaster verification token', 30),

  -- Contact form anti-spam (Cloudflare Turnstile) — keys only; SECRET lives in .env
  ('turnstile_enabled',   '0', 'bool',   'security', 'Enable Cloudflare Turnstile', 10),
  ('turnstile_site_key',  '',  'string', 'security', 'Turnstile Site Key',          20);
