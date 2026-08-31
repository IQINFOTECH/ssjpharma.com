<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap. Registers autoloading (Composer if present, first-party
 * fallback otherwise) so tests run with or without `vendor/`.
 */

$root = dirname(__DIR__);

$composer = $root . '/vendor/autoload.php';
if (is_file($composer)) {
    require $composer;
} else {
    require $root . '/app/Core/Autoloader.php';
    $loader = new App\Core\Autoloader();
    $loader->addNamespace('App', $root . '/app');
    $loader->addNamespace('Tests', $root . '/tests');
    $loader->register();
    require $root . '/app/Support/helpers.php';
}

// Deterministic environment for tests.
putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = 'testing';
