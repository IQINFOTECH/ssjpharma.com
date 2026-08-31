<?php

declare(strict_types=1);

/**
 * Application bootstrap.
 *
 * Wires autoloading (Composer if present, first-party fallback otherwise),
 * environment, configuration, the service container and routes, then returns
 * the App kernel. Used by public/index.php, bin/*.php and the test suite.
 */

use App\Auth\Auth;
use App\Auth\Rbac;
use App\Core\App as Kernel;
use App\Core\Autoloader;
use App\Core\Config;
use App\Core\Container;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Env;
use App\Core\Logger;
use App\Core\Middleware\SecurityHeaders;
use App\Core\Middleware\StartSession;
use App\Core\Middleware\VerifyCsrf;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;

// --- Paths -------------------------------------------------------------------
$root = dirname(__DIR__);
defined('BASE_PATH') || define('BASE_PATH', $root);
defined('APP_START') || define('APP_START', microtime(true));

// --- Autoloading -------------------------------------------------------------
$composerAutoload = $root . '/vendor/autoload.php';

if (is_file($composerAutoload)) {
    require $composerAutoload;
} else {
    // First-party fallback: guarantees boot without vendor/ (ADR-001).
    require $root . '/app/Core/Autoloader.php';
    $autoloader = new Autoloader();
    $autoloader->addNamespace('App', $root . '/app');
    $autoloader->register();
    require $root . '/app/Support/helpers.php';
}

// --- Environment -------------------------------------------------------------
Env::load($root . '/.env');

// Production safety net: APP_DEBUG is force-disabled when APP_ENV=production, so a
// mis-set .env can never expose stack traces / SQL / paths on the live site.
$isProduction = Env::get('APP_ENV', 'production') === 'production';
$debug = !$isProduction && (bool) Env::get('APP_DEBUG', false);
$isCli = PHP_SAPI === 'cli';

date_default_timezone_set((string) Env::get('APP_TIMEZONE', 'Asia/Kolkata'));
mb_internal_encoding('UTF-8');

// --- Error handling ----------------------------------------------------------
error_reporting(E_ALL);
ini_set('display_errors', $debug && $isCli ? '1' : '0');
ini_set('log_errors', '1');
ini_set('log_errors_max_len', '0');

// --- Container & service wiring ----------------------------------------------
$container = new Container();
Container::setInstance($container);

$container->instance(Container::class, $container);

$container->bind(Config::class, fn (): Config => new Config($root . '/config'));

$container->bind(Logger::class, function (Container $c) use ($root): Logger {
    $cfg = $c->get(Config::class);
    return new Logger(
        (string) $cfg->get('logging.path', $root . '/storage/logs'),
        (string) $cfg->get('logging.file', 'app-' . date('Y-m-d') . '.log'),
        (string) $cfg->get('logging.level', 'info'),
    );
});

$container->bind(Database::class, function (Container $c): Database {
    $cfg  = $c->get(Config::class);
    $name = (string) $cfg->get('database.default', 'mysql');
    return new Database((array) $cfg->get("database.connections.{$name}", []));
});

$container->bind(Session::class, function (Container $c): Session {
    return new Session((array) $c->get(Config::class)->get('security.session', []));
});

$container->bind(Csrf::class, fn (Container $c): Csrf => new Csrf($c->get(Session::class)));

$container->bind(View::class, fn (): View => new View($root . '/app/Views'));

$container->bind(Router::class, fn (): Router => new Router());

$container->bind(Auth::class, fn (Container $c): Auth => new Auth($c->get(Session::class)));

$container->bind(Rbac::class, fn (Container $c): Rbac => new Rbac($c->get(Auth::class)));

// Global middleware singletons
$container->bind(SecurityHeaders::class, fn (Container $c): SecurityHeaders => new SecurityHeaders(
    $c->get(Config::class),
    $c->get(App\Services\SettingsService::class),
));
$container->bind(StartSession::class, fn (Container $c): StartSession => new StartSession($c->get(Session::class)));
$container->bind(VerifyCsrf::class, fn (Container $c): VerifyCsrf => new VerifyCsrf($c->get(Csrf::class), $c->get(Config::class)));

// --- Phase 1: repositories -------------------------------------------------
$container->bind(App\Repositories\SettingRepository::class,     fn (Container $c) => new App\Repositories\SettingRepository($c->get(Database::class)));
$container->bind(App\Repositories\PageRepository::class,        fn (Container $c) => new App\Repositories\PageRepository($c->get(Database::class)));
$container->bind(App\Repositories\PageSectionRepository::class, fn (Container $c) => new App\Repositories\PageSectionRepository($c->get(Database::class)));
$container->bind(App\Repositories\MenuRepository::class,        fn (Container $c) => new App\Repositories\MenuRepository($c->get(Database::class)));
$container->bind(App\Repositories\MenuItemRepository::class,    fn (Container $c) => new App\Repositories\MenuItemRepository($c->get(Database::class)));
$container->bind(App\Repositories\MediaRepository::class,       fn (Container $c) => new App\Repositories\MediaRepository($c->get(Database::class)));
$container->bind(App\Repositories\RedirectRepository::class,    fn (Container $c) => new App\Repositories\RedirectRepository($c->get(Database::class)));
$container->bind(App\Repositories\LeadRepository::class,        fn (Container $c) => new App\Repositories\LeadRepository($c->get(Database::class)));
$container->bind(App\Repositories\UserRepository::class,        fn (Container $c) => new App\Repositories\UserRepository($c->get(Database::class)));
$container->bind(App\Repositories\RoleRepository::class,         fn (Container $c) => new App\Repositories\RoleRepository($c->get(Database::class)));
$container->bind(App\Repositories\PermissionRepository::class,   fn (Container $c) => new App\Repositories\PermissionRepository($c->get(Database::class)));
$container->bind(App\Repositories\AuditRepository::class,        fn (Container $c) => new App\Repositories\AuditRepository($c->get(Database::class)));
$container->bind(App\Repositories\PasswordResetRepository::class, fn (Container $c) => new App\Repositories\PasswordResetRepository($c->get(Database::class)));
$container->bind(App\Repositories\LoginAttemptRepository::class, fn (Container $c) => new App\Repositories\LoginAttemptRepository($c->get(Database::class)));
$container->bind(App\Repositories\UserSessionRepository::class,  fn (Container $c) => new App\Repositories\UserSessionRepository($c->get(Database::class)));
$container->bind(App\Repositories\DosageFormRepository::class,       fn (Container $c) => new App\Repositories\DosageFormRepository($c->get(Database::class)));
$container->bind(App\Repositories\ProductCategoryRepository::class,  fn (Container $c) => new App\Repositories\ProductCategoryRepository($c->get(Database::class)));
$container->bind(App\Repositories\TherapeuticAreaRepository::class,  fn (Container $c) => new App\Repositories\TherapeuticAreaRepository($c->get(Database::class)));
$container->bind(App\Repositories\ProductRepository::class,          fn (Container $c) => new App\Repositories\ProductRepository($c->get(Database::class)));
$container->bind(App\Repositories\LeadActivityRepository::class,     fn (Container $c) => new App\Repositories\LeadActivityRepository($c->get(Database::class)));
$container->bind(App\Repositories\WhatsappClickRepository::class,    fn (Container $c) => new App\Repositories\WhatsappClickRepository($c->get(Database::class)));
// --- Phase 5: communications repositories ----------------------------------
$container->bind(App\Repositories\EmailQueueRepository::class,          fn (Container $c) => new App\Repositories\EmailQueueRepository($c->get(Database::class)));
$container->bind(App\Repositories\EmailTemplateRepository::class,       fn (Container $c) => new App\Repositories\EmailTemplateRepository($c->get(Database::class)));
$container->bind(App\Repositories\WhatsappTemplateRepository::class,    fn (Container $c) => new App\Repositories\WhatsappTemplateRepository($c->get(Database::class)));
$container->bind(App\Repositories\CommunicationDigestRepository::class, fn (Container $c) => new App\Repositories\CommunicationDigestRepository($c->get(Database::class)));

// --- Phase 1: services -----------------------------------------------------
$container->bind(App\Services\SettingsService::class, fn (Container $c) => new App\Services\SettingsService(
    $c->get(App\Repositories\SettingRepository::class),
    $c->get(App\Repositories\MediaRepository::class),
));
$container->bind(App\Services\WhatsAppService::class, fn (Container $c) => new App\Services\WhatsAppService($c->get(App\Services\SettingsService::class)));
$container->bind(App\Services\MenuService::class, fn (Container $c) => new App\Services\MenuService(
    $c->get(App\Repositories\MenuRepository::class),
    $c->get(App\Repositories\MenuItemRepository::class),
));
$container->bind(App\Services\SeoService::class, fn (Container $c) => new App\Services\SeoService(
    $c->get(App\Services\SettingsService::class),
    $c->get(App\Repositories\MediaRepository::class),
));
$container->bind(App\Services\StructuredDataService::class, fn (Container $c) => new App\Services\StructuredDataService($c->get(App\Services\SettingsService::class)));
$container->bind(App\Services\RedirectService::class, fn (Container $c) => new App\Services\RedirectService($c->get(App\Repositories\RedirectRepository::class)));
$container->bind(App\Services\MediaService::class, fn (Container $c) => new App\Services\MediaService(
    $c->get(App\Repositories\MediaRepository::class),
    $c->get(Logger::class),
));
$container->bind(App\Services\MailService::class, fn (Container $c) => new App\Services\MailService($c->get(Config::class), $c->get(Logger::class), $c->get(View::class)));
$container->bind(App\Support\TemplateRenderer::class, fn (Container $c) => new App\Support\TemplateRenderer());
$container->bind(App\Services\EmailQueueService::class, fn (Container $c) => new App\Services\EmailQueueService(
    $c->get(App\Repositories\EmailQueueRepository::class),
    $c->get(App\Repositories\EmailTemplateRepository::class),
    $c->get(App\Repositories\LeadActivityRepository::class),
    $c->get(App\Support\TemplateRenderer::class),
    $c->get(App\Services\SettingsService::class),
    $c->get(Logger::class),
));
$container->bind(App\Services\CaptchaService::class, fn (Container $c) => new App\Services\CaptchaService($c->get(App\Services\SettingsService::class), $c->get(Logger::class)));
$container->bind(App\Services\LeadService::class, fn (Container $c) => new App\Services\LeadService(
    $c->get(App\Repositories\LeadRepository::class),
    $c->get(App\Repositories\LeadActivityRepository::class),
    $c->get(App\Repositories\ProductRepository::class),
    $c->get(App\Services\SettingsService::class),
    $c->get(App\Services\EmailQueueService::class),
    $c->get(Database::class),
    $c->get(Logger::class),
));
$container->bind(App\Services\AuditService::class, fn (Container $c) => new App\Services\AuditService(
    $c->get(App\Repositories\AuditRepository::class),
    $c,
));
$container->bind(App\Services\ThrottleService::class, fn (Container $c) => new App\Services\ThrottleService(
    $c->get(App\Repositories\LoginAttemptRepository::class),
    $c->get(Config::class),
));
$container->bind(App\Services\AuthService::class, fn (Container $c) => new App\Services\AuthService(
    $c->get(App\Repositories\UserRepository::class),
    $c->get(Auth::class),
    $c->get(Config::class),
    $c->get(Logger::class),
    $c->get(App\Services\ThrottleService::class),
    $c->get(App\Services\AuditService::class),
    $c->get(App\Repositories\UserSessionRepository::class),
    $c->get(Session::class),
));
$container->bind(App\Services\PasswordResetService::class, fn (Container $c) => new App\Services\PasswordResetService(
    $c->get(App\Repositories\UserRepository::class),
    $c->get(App\Repositories\PasswordResetRepository::class),
    $c->get(App\Repositories\UserSessionRepository::class),
    $c->get(App\Services\MailService::class),
    $c->get(App\Services\AuditService::class),
    $c->get(App\Services\SettingsService::class),
    $c->get(Config::class),
    $c->get(Logger::class),
));
$container->bind(App\Services\UserService::class, fn (Container $c) => new App\Services\UserService(
    $c->get(App\Repositories\UserRepository::class),
    $c->get(App\Repositories\RoleRepository::class),
    $c->get(App\Repositories\UserSessionRepository::class),
    $c->get(App\Services\AuditService::class),
    $c->get(Config::class),
));
$container->bind(App\Services\RoleService::class, fn (Container $c) => new App\Services\RoleService(
    $c->get(App\Repositories\RoleRepository::class),
    $c->get(App\Repositories\PermissionRepository::class),
    $c->get(App\Services\AuditService::class),
));
$container->bind(App\Services\ProductCategoryService::class, fn (Container $c) => new App\Services\ProductCategoryService(
    $c->get(App\Repositories\ProductCategoryRepository::class),
    $c->get(App\Repositories\RedirectRepository::class),
    $c->get(App\Services\AuditService::class),
    $c->get(App\Repositories\MediaRepository::class),
));
$container->bind(App\Services\TherapeuticAreaService::class, fn (Container $c) => new App\Services\TherapeuticAreaService(
    $c->get(App\Repositories\TherapeuticAreaRepository::class),
    $c->get(App\Repositories\RedirectRepository::class),
    $c->get(App\Services\AuditService::class),
    $c->get(App\Repositories\MediaRepository::class),
));
$container->bind(App\Services\ProductService::class, fn (Container $c) => new App\Services\ProductService(
    $c->get(App\Repositories\ProductRepository::class),
    $c->get(App\Repositories\ProductCategoryRepository::class),
    $c->get(App\Repositories\DosageFormRepository::class),
    $c->get(App\Repositories\TherapeuticAreaRepository::class),
    $c->get(App\Repositories\RedirectRepository::class),
    $c->get(App\Services\MediaService::class),
    $c->get(App\Services\AuditService::class),
    $c->get(Logger::class),
    $c->get(App\Repositories\MediaRepository::class),
));

// --- Runtime error/exception handlers (report cleanly, log always) -----------
set_exception_handler(function (Throwable $e) use ($container, $debug, $isCli): void {
    $container->get(Logger::class)->critical('Uncaught {class}: {message} in {file}:{line}', [
        'class' => $e::class, 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(),
    ]);
    if ($isCli) {
        fwrite(STDERR, ($debug ? (string) $e : 'Fatal error.') . PHP_EOL);
    } else {
        http_response_code(500);
        echo $debug ? '<pre>' . e((string) $e) . '</pre>' : 'Server error.';
    }
});

set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// --- Routes ------------------------------------------------------------------
$router = $container->get(Router::class);
(function (Router $router, Container $container) use ($root): void {
    require $root . '/routes/web.php';
})($router, $container);

// --- Kernel ------------------------------------------------------------------
return new Kernel($container, $router);
