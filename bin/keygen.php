<?php

declare(strict_types=1);

/**
 * Generate a cryptographically-secure APP_KEY for .env.
 * Usage:  php bin/keygen.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("keygen.php is a CLI script.\n");
}

echo 'base64:' . base64_encode(random_bytes(32)) . PHP_EOL;
