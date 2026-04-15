<?php

declare(strict_types=1);

/**
 * Simple PHP connection test for a Supabase PostgreSQL database
 * using DATABASE_URL from .env
 */

function loadEnv(string $path): void
{
    if (!file_exists($path)) {
        throw new RuntimeException(".env file not found at: {$path}");
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines === false) {
        throw new RuntimeException("Unable to read .env file.");
    }

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');

        $key = trim($key);
        $value = trim($value);

        // Remove wrapping quotes if present
        $value = trim($value, "\"'");

        $_ENV[$key] = $value;
        putenv("$key=$value");
    }
}

try {
    loadEnv(__DIR__ . '/.env');

    $databaseUrl = $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL');

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
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}