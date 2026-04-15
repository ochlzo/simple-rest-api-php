<?php

declare(strict_types=1);

$autoloadPath = __DIR__ . '/vendor/autoload.php';

if (!file_exists($autoloadPath)) {
    throw new RuntimeException(
        'Composer dependencies are missing. Run `composer install` first.'
    );
}

require $autoloadPath;
require __DIR__ . '/app.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

routeRequest();
