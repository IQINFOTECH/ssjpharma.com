-- Phase 2 RBAC catalogue (idempotent). Six DB-driven roles + granular permissions
-- + a configurable role→permission matrix. super_admin is a code-level wildcard;
-- its grants are seeded for display/config completeness. The admin USER is created
-- via bin/create-admin.php (password hashed there, never stored in a seed).
-- Re-runnable safely; upgrades a Phase 1 database to the Phase 2 model.

-- 1) Roles ------------------------------------------------------------------
INSERT IGNORE INTO `roles` (`key`,`name`,`description`,`is_system`,`is_active`) VALUES
  ('super_admin',     'Super Admin',      'Full, unrestricted access',                 1, 1),
  ('admin',           'Admin',            'Administrative access (not role/permission definitions)', 1, 1),
  ('sales_manager',   'Sales Manager',    'Leads, CRM and reports',                    1, 1),
  ('sales_executive', 'Sales Executive',  'Assigned leads and follow-ups',             1, 1),
  ('product_manager', 'Product Manager',  'Products, categories and therapeutic areas',1, 1),
  ('content_manager', 'Content Manager',  'Pages, menus, media and redirects',         1, 1);

-- Keep system roles active; deactivate deprecated Phase 1 roles (non-destructive).
UPDATE `roles` SET `is_active` = 1 WHERE `key` IN
  ('super_admin','admin','sales_manager','sales_executive','product_manager','content_manager');
UPDATE `roles` SET `is_active` = 0 WHERE `key` IN ('content_editor','sales_agent');

-- 2) Remove deprecated coarse Phase 1 permissions (role_permissions cascade) --
DELETE FROM `permissions` WHERE `key` IN
  ('cms.access','pages.manage','menus.manage','media.manage','redirects.manage','settings.manage','leads.manage');

-- 3) Granular permission catalogue ------------------------------------------
INSERT IGNORE INTO `permissions` (`key`,`name`,`group`) VALUES
  ('dashboard.view',   'View dashboard',      'dashboard'),
  ('users.view',       'View users',          'users'),
  ('users.create',     'Create users',        'users'),
  ('users.edit',       'Edit users',          'users'),
  ('users.delete',     'Delete users',        'users'),
  ('users.activate',   'Activate/deactivate users', 'users'),
  ('roles.view',       'View roles',          'roles'),
  ('roles.create',     'Create roles',        'roles'),
  ('roles.edit',       'Edit roles & permissions', 'roles'),
  ('roles.delete',     'Delete roles',        'roles'),
  ('pages.view',       'View pages',          'pages'),
  ('pages.create',     'Create pages',        'pages'),
  ('pages.edit',       'Edit pages',          'pages'),
  ('pages.publish',    'Publish/unpublish pages', 'pages'),
  ('pages.delete',     'Delete pages',        'pages'),
  ('menus.view',       'View menus',          'menus'),
  ('menus.create',     'Create menu items',   'menus'),
  ('menus.edit',       'Edit menu items',     'menus'),
  ('menus.delete',     'Delete menu items',   'menus'),
  ('media.view',       'View media',          'media'),
  ('media.upload',     'Upload media',        'media'),
  ('media.delete',     'Delete media',        'media'),
  ('redirects.view',   'View redirects',      'redirects'),
  ('redirects.create', 'Create redirects',    'redirects'),
  ('redirects.edit',   'Edit redirects',      'redirects'),
  ('redirects.delete', 'Delete redirects',    'redirects'),
  ('settings.view',    'View settings',       'settings'),
  ('settings.edit',    'Edit settings',       'settings'),
  ('leads.view',          'Access lead module',        'leads'),
  ('leads.view_all',      'View all leads',            'leads'),
  ('leads.view_assigned', 'View only assigned leads',  'leads'),
  ('leads.create',     'Create leads',        'leads'),
  ('leads.edit',       'Edit leads',          'leads'),
  ('leads.assign',     'Assign leads',        'leads'),
  ('leads.delete',     'Delete leads',        'leads'),
  ('leads.export',     'Export leads',        'leads'),
  ('leads.notes',      'Add lead notes',      'leads'),
  ('leads.status',     'Change lead status',  'leads'),
  ('leads.priority',   'Change lead priority','leads'),
  ('products.view',    'View products',       'products'),
  ('products.create',  'Create products',     'products'),
  ('products.edit',    'Edit products',       'products'),
  ('products.review',  'Review/approve products', 'products'),
  ('products.publish', 'Publish products',    'products'),
  ('products.archive', 'Archive products',    'products'),
  ('products.delete',  'Delete products',     'products'),
  ('reports.view',     'View reports',        'reports'),
  ('reports.export',   'Export reports',      'reports'),
  ('audit.view',       'View audit log',      'audit'),
  ('communications.view',             'View communications / email queue', 'communications'),
  ('communications.retry',            'Retry / cancel queued email',       'communications'),
  ('communications.manage_templates', 'Manage email/WhatsApp templates',   'communications'),
  ('communications.send_test',        'Send test email',                   'communications');

-- 4) Role → permission matrix (INSERT IGNORE; configurable later in /admin/roles)

-- super_admin: everything (wildcard in code; grants for display/config).
INSERT IGNORE INTO `role_permissions` (`role_id`,`permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p WHERE r.`key`='super_admin';

-- admin: everything EXCEPT redefining roles/permissions (roles.create/edit/delete).
INSERT IGNORE INTO `role_permissions` (`role_id`,`permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p
WHERE r.`key`='admin' AND p.`key` NOT IN ('roles.create','roles.edit','roles.delete');

-- sales_manager: dashboard + ALL leads (leads.view_all) + reports.
INSERT IGNORE INTO `role_permissions` (`role_id`,`permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p
WHERE r.`key`='sales_manager' AND p.`key` IN
  ('dashboard.view','leads.view','leads.view_all','leads.create','leads.edit','leads.assign','leads.delete','leads.export',
   'leads.notes','leads.status','leads.priority','reports.view','reports.export');

-- sales_executive: dashboard + ASSIGNED-only leads (leads.view_assigned; no delete/assign/export).
-- Retains the note/status/priority actions it already had under leads.edit.
INSERT IGNORE INTO `role_permissions` (`role_id`,`permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p
WHERE r.`key`='sales_executive' AND p.`key` IN
  ('dashboard.view','leads.view','leads.view_assigned','leads.create','leads.edit',
   'leads.notes','leads.status','leads.priority');

-- product_manager: dashboard + products + media (view/upload) + reports.view.
INSERT IGNORE INTO `role_permissions` (`role_id`,`permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p
WHERE r.`key`='product_manager' AND p.`key` IN
  ('dashboard.view','products.view','products.create','products.edit','products.review','products.publish','products.archive','products.delete','media.view','media.upload','reports.view');

-- content_manager: dashboard + pages + menus + media + redirects.view.
INSERT IGNORE INTO `role_permissions` (`role_id`,`permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p
WHERE r.`key`='content_manager' AND p.`key` IN
  ('dashboard.view','pages.view','pages.create','pages.edit','pages.publish','pages.delete',
   'menus.view','menus.create','menus.edit','menus.delete',
   'media.view','media.upload','media.delete','redirects.view');
