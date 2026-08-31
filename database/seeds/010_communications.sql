-- Phase 5 communications seed (idempotent). Follow-up digest setting + default
-- email/WhatsApp templates. Placeholders are {{dotted.keys}} replaced safely at
-- render time (no PHP/JS/SQL execution). Lead values are HTML-escaped in html body.

-- Settings (group "communications"). Acknowledgement reuses the existing
-- lead_autoreply_enabled toggle from seed 007; here we add the digest toggle.
INSERT IGNORE INTO `settings` (`key`,`value`,`type`,`group`,`label`,`sort_order`) VALUES
  ('lead_followup_digest_enabled', '1', 'bool', 'communications', 'Send daily follow-up digests', 10);

-- --- Default email templates -------------------------------------------------
INSERT IGNORE INTO `email_templates` (`key`,`name`,`subject`,`body_html`,`body_text`) VALUES
(
  'lead_internal_notification',
  'Internal: New Lead Notification',
  'New {{lead.enquiry_type}} enquiry — {{lead.name}} [{{lead.reference}}]',
  '<h2 style="margin:0 0 12px;font-family:Arial,sans-serif;color:#0f172a">New website enquiry</h2>
<p style="font-family:Arial,sans-serif;color:#334155">A new lead has been captured on {{site.name}}.</p>
<table cellpadding="6" style="font-family:Arial,sans-serif;color:#334155;border-collapse:collapse">
<tr><td style="color:#64748b">Reference</td><td><strong>{{lead.reference}}</strong></td></tr>
<tr><td style="color:#64748b">Name</td><td>{{lead.name}}</td></tr>
<tr><td style="color:#64748b">Company</td><td>{{lead.company}}</td></tr>
<tr><td style="color:#64748b">Email</td><td>{{lead.email}}</td></tr>
<tr><td style="color:#64748b">Phone</td><td>{{lead.phone}}</td></tr>
<tr><td style="color:#64748b">Enquiry type</td><td>{{lead.enquiry_type}}</td></tr>
<tr><td style="color:#64748b">Product</td><td>{{lead.product_name}}</td></tr>
<tr><td style="color:#64748b">Requirement</td><td>{{lead.requirement}}</td></tr>
<tr><td style="color:#64748b">Message</td><td>{{lead.message}}</td></tr>
<tr><td style="color:#64748b">Source</td><td>{{lead.source}}</td></tr>
<tr><td style="color:#64748b">Landing page</td><td>{{lead.landing_page}}</td></tr>
<tr><td style="color:#64748b">Campaign</td><td>{{lead.utm_source}} / {{lead.utm_medium}} / {{lead.utm_campaign}}</td></tr>
</table>
<p style="font-family:Arial,sans-serif"><a href="{{lead.url}}">Open this lead in the admin</a></p>',
  'New website enquiry on {{site.name}}

Reference: {{lead.reference}}
Name: {{lead.name}}
Company: {{lead.company}}
Email: {{lead.email}}
Phone: {{lead.phone}}
Enquiry type: {{lead.enquiry_type}}
Product: {{lead.product_name}}
Requirement: {{lead.requirement}}
Message: {{lead.message}}
Source: {{lead.source}}
Landing page: {{lead.landing_page}}
Campaign: {{lead.utm_source}} / {{lead.utm_medium}} / {{lead.utm_campaign}}

Open in admin: {{lead.url}}'
),
(
  'lead_customer_acknowledgement',
  'Customer: Enquiry Acknowledgement',
  'We have received your enquiry — {{site.name}}',
  '<p style="font-family:Arial,sans-serif;color:#334155">Dear {{lead.name}},</p>
<p style="font-family:Arial,sans-serif;color:#334155">Thank you for contacting {{site.name}}. We have received your enquiry and our team will review it. This is an automated acknowledgement.</p>
<p style="font-family:Arial,sans-serif;color:#64748b">Your reference: {{lead.reference}}</p>
<p style="font-family:Arial,sans-serif;color:#334155">Regards,<br>{{site.name}}</p>',
  'Dear {{lead.name}},

Thank you for contacting {{site.name}}. We have received your enquiry and our team will review it. This is an automated acknowledgement.

Your reference: {{lead.reference}}

Regards,
{{site.name}}'
),
(
  'followup_due_digest',
  'Internal: Follow-up Due Digest',
  'Your follow-ups due ({{followups.count}}) — {{site.name}}',
  '<h2 style="margin:0 0 12px;font-family:Arial,sans-serif;color:#0f172a">Follow-ups due</h2>
<p style="font-family:Arial,sans-serif;color:#334155">Hello {{assignee.name}}, you have {{followups.count}} lead(s) due for follow-up.</p>
{{followups.rows}}
<p style="font-family:Arial,sans-serif;color:#94a3b8">This is an automated reminder from {{site.name}}.</p>',
  'Hello {{assignee.name}}, you have {{followups.count}} lead(s) due for follow-up.

{{followups.rows_text}}

This is an automated reminder from {{site.name}}.'
);

-- --- Default WhatsApp templates (wa.me only; user-initiated) ------------------
INSERT IGNORE INTO `whatsapp_templates` (`key`,`name`,`message`) VALUES
  ('general_enquiry',     'General Enquiry',     'Hello {{site.name}}, I would like to know more about your products.'),
  ('product_enquiry',     'Product Enquiry',     'Hello {{site.name}}, I am interested in {{product.name}}. Please share more information.'),
  ('distributor_enquiry', 'Distributor Enquiry', 'Hello {{site.name}}, I would like to discuss distributor / partnership opportunities.');
