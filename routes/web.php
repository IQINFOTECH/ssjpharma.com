<?php

declare(strict_types=1);

/**
 * Web routes (Phase 2).
 * Available in scope: $router (App\Core\Router), $container (App\Core\Container).
 *
 * Authorization model for /admin:  authenticate (auth) → track/revoke session
 * (track_session) → forced-password gate (must_change) → per-route permission
 * (can:*) → controller re-check (defence in depth) → service.
 *
 * @var App\Core\Router    $router
 * @var App\Core\Container $container
 */

use App\Controllers\HealthController;
use App\Controllers\Site\ContactController;
use App\Controllers\Site\PageController;
use App\Controllers\Site\SeoController;
use App\Controllers\Site\CatalogController;
use App\Controllers\Site\WhatsAppController;
use App\Controllers\Admin\LeadsController;
use App\Controllers\Admin\EmailQueueController;
use App\Controllers\Admin\CommunicationTemplatesController;
use App\Controllers\Admin\ProductsController;
use App\Controllers\Admin\ProductCategoriesController;
use App\Controllers\Admin\TherapeuticAreasController;
use App\Controllers\Admin\DosageFormsController;
use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\PasswordResetController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\PagesController;
use App\Controllers\Admin\MenusController;
use App\Controllers\Admin\MediaController;
use App\Controllers\Admin\RedirectsController;
use App\Controllers\Admin\SettingsController;
use App\Controllers\Admin\UsersController;
use App\Controllers\Admin\RolesController;
use App\Controllers\Admin\AuditController;
use App\Controllers\Admin\ProfileController;
use App\Controllers\Admin\SessionsController;

/* ----------------------------------------------------------------------------
 | Public
 * ------------------------------------------------------------------------- */
$router->get('/health', [HealthController::class, 'index'], ['name' => 'health']);
$router->get('/', [PageController::class, 'home'], ['name' => 'home']);
$router->get('/sitemap.xml', [SeoController::class, 'sitemap']);
$router->get('/robots.txt', [SeoController::class, 'robots']);
$router->post('/contact', [ContactController::class, 'submit'], ['name' => 'contact.submit']);
$router->post('/whatsapp/track', [WhatsAppController::class, 'track']); // WhatsApp CTA click beacon

// Public product catalog (registered before the catch-all).
$router->get('/products', [CatalogController::class, 'index'], ['name' => 'products']);
$router->get('/products/{slug}', [CatalogController::class, 'product'], ['name' => 'product.show']);
$router->get('/product-category/{slug}', [CatalogController::class, 'category'], ['name' => 'product.category']);
$router->get('/therapeutic-area/{slug}', [CatalogController::class, 'therapeuticArea'], ['name' => 'therapeutic.area']);

/* ----------------------------------------------------------------------------
 | Admin — authentication + password reset (public)
 * ------------------------------------------------------------------------- */
$router->get('/admin/login', [AuthController::class, 'showLogin'], ['name' => 'admin.login']);
$router->post('/admin/login', [AuthController::class, 'login']);
$router->get('/admin/forgot-password', [PasswordResetController::class, 'showForgot']);
$router->post('/admin/forgot-password', [PasswordResetController::class, 'forgot']);
$router->get('/admin/reset-password', [PasswordResetController::class, 'showReset']);
$router->post('/admin/reset-password', [PasswordResetController::class, 'reset']);

/* ----------------------------------------------------------------------------
 | Admin — authenticated area
 * ------------------------------------------------------------------------- */
$router->group(['prefix' => 'admin', 'middleware' => ['auth', 'track_session', 'must_change']], function (App\Core\Router $r): void {
    $r->post('/logout', [AuthController::class, 'logout']);
    $r->get('/password', [AuthController::class, 'showPasswordChange']);
    $r->post('/password', [AuthController::class, 'updatePassword']);

    $r->get('/', [DashboardController::class, 'index'], ['middleware' => ['can:dashboard.view']]);

    // Profile & sessions (own account — no special permission required)
    $r->get('/profile', [ProfileController::class, 'show']);
    $r->post('/profile', [ProfileController::class, 'update']);
    $r->get('/sessions', [SessionsController::class, 'index']);
    $r->post('/sessions/{id}/revoke', [SessionsController::class, 'revoke']);

    // Users
    $r->get('/users', [UsersController::class, 'index'], ['middleware' => ['can:users.view']]);
    $r->get('/users/create', [UsersController::class, 'create'], ['middleware' => ['can:users.create']]);
    $r->post('/users', [UsersController::class, 'store'], ['middleware' => ['can:users.create']]);
    $r->get('/users/{id}/edit', [UsersController::class, 'edit'], ['middleware' => ['can:users.view']]);
    $r->put('/users/{id}', [UsersController::class, 'update'], ['middleware' => ['can:users.edit']]);
    $r->post('/users/{id}/active', [UsersController::class, 'setActive'], ['middleware' => ['can:users.activate']]);
    $r->post('/users/{id}/reset-password', [UsersController::class, 'resetPassword'], ['middleware' => ['can:users.edit']]);
    $r->delete('/users/{id}/delete', [UsersController::class, 'destroy'], ['middleware' => ['can:users.delete']]);
    $r->get('/users/{id}/activity', [UsersController::class, 'activity'], ['middleware' => ['can:users.view']]);

    // Roles & permission matrix
    $r->get('/roles', [RolesController::class, 'index'], ['middleware' => ['can:roles.view']]);
    $r->post('/roles', [RolesController::class, 'store'], ['middleware' => ['can:roles.create']]);
    $r->get('/roles/{id}/edit', [RolesController::class, 'edit'], ['middleware' => ['can:roles.view']]);
    $r->put('/roles/{id}', [RolesController::class, 'update'], ['middleware' => ['can:roles.edit']]);
    $r->post('/roles/{id}/permissions', [RolesController::class, 'setPermissions'], ['middleware' => ['can:roles.edit']]);
    $r->delete('/roles/{id}/delete', [RolesController::class, 'destroy'], ['middleware' => ['can:roles.delete']]);

    // Audit log (read-only)
    $r->get('/audit-logs', [AuditController::class, 'index'], ['middleware' => ['can:audit.view']]);
    $r->get('/audit-logs/{id}', [AuditController::class, 'show'], ['middleware' => ['can:audit.view']]);

    // Pages
    $r->get('/pages', [PagesController::class, 'index'], ['middleware' => ['can:pages.view']]);
    $r->get('/pages/create', [PagesController::class, 'create'], ['middleware' => ['can:pages.create']]);
    $r->post('/pages', [PagesController::class, 'store'], ['middleware' => ['can:pages.create']]);
    $r->get('/pages/{id}/edit', [PagesController::class, 'edit'], ['middleware' => ['can:pages.view']]);
    $r->put('/pages/{id}', [PagesController::class, 'update'], ['middleware' => ['can:pages.edit']]);
    $r->post('/pages/{id}/status', [PagesController::class, 'status'], ['middleware' => ['can:pages.publish']]);
    $r->delete('/pages/{id}/delete', [PagesController::class, 'destroy'], ['middleware' => ['can:pages.delete']]);
    $r->post('/pages/{id}/sections', [PagesController::class, 'addSection'], ['middleware' => ['can:pages.edit']]);
    $r->put('/sections/{id}', [PagesController::class, 'updateSection'], ['middleware' => ['can:pages.edit']]);
    $r->post('/sections/{id}/delete', [PagesController::class, 'deleteSection'], ['middleware' => ['can:pages.edit']]);

    // Menus
    $r->get('/menus', [MenusController::class, 'index'], ['middleware' => ['can:menus.view']]);
    $r->post('/menus/{menu}/items', [MenusController::class, 'addItem'], ['middleware' => ['can:menus.create']]);
    $r->put('/menu-items/{id}', [MenusController::class, 'updateItem'], ['middleware' => ['can:menus.edit']]);
    $r->post('/menu-items/{id}/delete', [MenusController::class, 'deleteItem'], ['middleware' => ['can:menus.delete']]);

    // Media
    $r->get('/media', [MediaController::class, 'index'], ['middleware' => ['can:media.view']]);
    $r->post('/media', [MediaController::class, 'upload'], ['middleware' => ['can:media.upload']]);
    $r->put('/media/{id}', [MediaController::class, 'updateMeta'], ['middleware' => ['can:media.upload']]);
    $r->post('/media/{id}/delete', [MediaController::class, 'delete'], ['middleware' => ['can:media.delete']]);

    // Redirects
    $r->get('/redirects', [RedirectsController::class, 'index'], ['middleware' => ['can:redirects.view']]);
    $r->post('/redirects', [RedirectsController::class, 'store'], ['middleware' => ['can:redirects.create']]);
    $r->put('/redirects/{id}', [RedirectsController::class, 'update'], ['middleware' => ['can:redirects.edit']]);
    $r->post('/redirects/{id}/delete', [RedirectsController::class, 'delete'], ['middleware' => ['can:redirects.delete']]);

    // Settings
    $r->get('/settings', [SettingsController::class, 'index'], ['middleware' => ['can:settings.view']]);
    $r->post('/settings', [SettingsController::class, 'update'], ['middleware' => ['can:settings.edit']]);

    // --- Lead management (Phase 4) ---
    $r->get('/leads', [LeadsController::class, 'index'], ['middleware' => ['can:leads.view']]);
    $r->get('/leads/export', [LeadsController::class, 'export'], ['middleware' => ['can:leads.export']]);
    $r->get('/leads/{id:\d+}', [LeadsController::class, 'show'], ['middleware' => ['can:leads.view']]);
    $r->post('/leads/{id:\d+}/status', [LeadsController::class, 'updateStatus'], ['middleware' => ['can:leads.status']]);
    $r->post('/leads/{id:\d+}/priority', [LeadsController::class, 'updatePriority'], ['middleware' => ['can:leads.priority']]);
    $r->post('/leads/{id:\d+}/assign', [LeadsController::class, 'assign'], ['middleware' => ['can:leads.assign']]);
    $r->post('/leads/{id:\d+}/notes', [LeadsController::class, 'addNote'], ['middleware' => ['can:leads.notes']]);
    $r->post('/leads/{id:\d+}/contacted', [LeadsController::class, 'markContacted'], ['middleware' => ['can:leads.edit']]);
    $r->post('/leads/{id:\d+}/followup', [LeadsController::class, 'updateFollowUp'], ['middleware' => ['can:leads.edit']]);
    $r->delete('/leads/{id:\d+}/delete', [LeadsController::class, 'destroy'], ['middleware' => ['can:leads.delete']]);

    // --- Communications (Phase 5) ---
    $r->get('/email-queue', [EmailQueueController::class, 'index'], ['middleware' => ['can:communications.view']]);
    $r->get('/email-queue/{id:\d+}', [EmailQueueController::class, 'show'], ['middleware' => ['can:communications.view']]);
    $r->post('/email-queue/{id:\d+}/retry', [EmailQueueController::class, 'retry'], ['middleware' => ['can:communications.retry']]);
    $r->post('/email-queue/{id:\d+}/cancel', [EmailQueueController::class, 'cancel'], ['middleware' => ['can:communications.retry']]);

    $r->get('/communications/templates', [CommunicationTemplatesController::class, 'index'], ['middleware' => ['can:communications.manage_templates']]);
    $r->get('/communications/email-templates/{id:\d+}/edit', [CommunicationTemplatesController::class, 'editEmail'], ['middleware' => ['can:communications.manage_templates']]);
    $r->post('/communications/email-templates/{id:\d+}', [CommunicationTemplatesController::class, 'updateEmail'], ['middleware' => ['can:communications.manage_templates']]);
    $r->post('/communications/email-templates/{id:\d+}/preview', [CommunicationTemplatesController::class, 'previewEmail'], ['middleware' => ['can:communications.manage_templates']]);
    $r->post('/communications/email-templates/{id:\d+}/test', [CommunicationTemplatesController::class, 'testEmail'], ['middleware' => ['can:communications.send_test']]);
    $r->get('/communications/whatsapp-templates/{id:\d+}/edit', [CommunicationTemplatesController::class, 'editWhatsapp'], ['middleware' => ['can:communications.manage_templates']]);
    $r->post('/communications/whatsapp-templates/{id:\d+}', [CommunicationTemplatesController::class, 'updateWhatsapp'], ['middleware' => ['can:communications.manage_templates']]);

    // --- Product catalog (Phase 3) ---
    // Products
    $r->get('/products', [ProductsController::class, 'index'], ['middleware' => ['can:products.view']]);
    $r->get('/products/create', [ProductsController::class, 'create'], ['middleware' => ['can:products.create']]);
    $r->post('/products', [ProductsController::class, 'store'], ['middleware' => ['can:products.create']]);
    $r->get('/products/{id}/edit', [ProductsController::class, 'edit'], ['middleware' => ['can:products.view']]);
    $r->put('/products/{id}', [ProductsController::class, 'update'], ['middleware' => ['can:products.edit']]);
    $r->post('/products/{id}/status', [ProductsController::class, 'status'], ['middleware' => ['can:products.publish']]);
    $r->post('/products/{id}/duplicate', [ProductsController::class, 'duplicate'], ['middleware' => ['can:products.create']]);
    $r->delete('/products/{id}/delete', [ProductsController::class, 'destroy'], ['middleware' => ['can:products.delete']]);
    $r->post('/products/{id}/images', [ProductsController::class, 'uploadImage'], ['middleware' => ['can:products.edit']]);
    $r->post('/products/{id}/images/{imageId}/primary', [ProductsController::class, 'setPrimaryImage'], ['middleware' => ['can:products.edit']]);
    $r->post('/products/{id}/images/{imageId}/delete', [ProductsController::class, 'deleteImage'], ['middleware' => ['can:products.edit']]);
    $r->post('/products/{id}/documents', [ProductsController::class, 'uploadDocument'], ['middleware' => ['can:products.edit']]);
    $r->post('/products/{id}/documents/{docId}/delete', [ProductsController::class, 'deleteDocument'], ['middleware' => ['can:products.edit']]);

    // Product categories
    $r->get('/product-categories', [ProductCategoriesController::class, 'index'], ['middleware' => ['can:products.view']]);
    $r->get('/product-categories/create', [ProductCategoriesController::class, 'create'], ['middleware' => ['can:products.create']]);
    $r->post('/product-categories', [ProductCategoriesController::class, 'store'], ['middleware' => ['can:products.create']]);
    $r->get('/product-categories/{id}/edit', [ProductCategoriesController::class, 'edit'], ['middleware' => ['can:products.view']]);
    $r->put('/product-categories/{id}', [ProductCategoriesController::class, 'update'], ['middleware' => ['can:products.edit']]);
    $r->post('/product-categories/{id}/status', [ProductCategoriesController::class, 'status'], ['middleware' => ['can:products.publish']]);
    $r->delete('/product-categories/{id}/delete', [ProductCategoriesController::class, 'destroy'], ['middleware' => ['can:products.delete']]);

    // Therapeutic areas
    $r->get('/therapeutic-areas', [TherapeuticAreasController::class, 'index'], ['middleware' => ['can:products.view']]);
    $r->get('/therapeutic-areas/create', [TherapeuticAreasController::class, 'create'], ['middleware' => ['can:products.create']]);
    $r->post('/therapeutic-areas', [TherapeuticAreasController::class, 'store'], ['middleware' => ['can:products.create']]);
    $r->get('/therapeutic-areas/{id}/edit', [TherapeuticAreasController::class, 'edit'], ['middleware' => ['can:products.view']]);
    $r->put('/therapeutic-areas/{id}', [TherapeuticAreasController::class, 'update'], ['middleware' => ['can:products.edit']]);
    $r->post('/therapeutic-areas/{id}/status', [TherapeuticAreasController::class, 'status'], ['middleware' => ['can:products.publish']]);
    $r->delete('/therapeutic-areas/{id}/delete', [TherapeuticAreasController::class, 'destroy'], ['middleware' => ['can:products.delete']]);

    // Dosage forms
    $r->get('/dosage-forms', [DosageFormsController::class, 'index'], ['middleware' => ['can:products.view']]);
    $r->post('/dosage-forms', [DosageFormsController::class, 'store'], ['middleware' => ['can:products.edit']]);
    $r->put('/dosage-forms/{id}', [DosageFormsController::class, 'update'], ['middleware' => ['can:products.edit']]);
    $r->post('/dosage-forms/{id}/delete', [DosageFormsController::class, 'delete'], ['middleware' => ['can:products.edit']]);
});

/* ----------------------------------------------------------------------------
 | Public catch-all — CMS redirects, then published pages, else 404. Last.
 * ------------------------------------------------------------------------- */
$router->get('/{path:.+}', [PageController::class, 'show']);
