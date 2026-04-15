<?php

declare(strict_types=1);

/**
 * Simple PHP connection test for a Supabase PostgreSQL database
 * using DATABASE_URL from .env
 */

$autoloadPath = __DIR__ . '/vendor/autoload.php';

if (!file_exists($autoloadPath)) {
    throw new RuntimeException(
        'Composer dependencies are missing. Run `composer install` first.'
    );
}

require $autoloadPath;

try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad();

    $databaseUrl = $_ENV['DATABASE_URL'] ?? $_SERVER['DATABASE_URL'] ?? getenv('DATABASE_URL');

    if (!$databaseUrl) {
        throw new RuntimeException('DATABASE_URL is missing.');
    }

    $parts = parse_url($databaseUrl);

    if ($parts === false) {
        throw new RuntimeException('Invalid DATABASE_URL format.');
    }

    $host = $parts['host'] ?? '';
    $port = $parts['port'] ?? 5432;
    $user = $parts['user'] ?? '';
    $pass = $parts['pass'] ?? '';
    $db   = isset($parts['path']) ? ltrim($parts['path'], '/') : '';

    parse_str($parts['query'] ?? '', $queryParams);

    $sslmode = $queryParams['sslmode'] ?? 'require';

    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
        $host,
        $port,
        $db,
        $sslmode
    );

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $stmt = $pdo->query('SELECT NOW() AS current_time');
    $result = $stmt->fetch();

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Connected to Supabase successfully.',
        'server_time' => $result['current_time'] ?? null,
    ], JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ], JSON_PRETTY_PRINT);
}
