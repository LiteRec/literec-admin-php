<?php

use Symfony\Component\Dotenv\Dotenv;

require_once dirname(__DIR__) . '/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__) . '/.env');

if ($_SERVER['APP_DEBUG'] ?? false) {
    // NOSONAR — debug-only test bootstrap; group-write mask is intentional for shared dev/CI caches.
    umask(0002); // NOSONAR
}
