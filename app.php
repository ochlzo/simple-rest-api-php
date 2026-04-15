<?php

declare(strict_types=1);

function normalizeRequestPath(string $path): string
{
    $path = rtrim($path, '/');

    return $path === '' ? '/' : $path;
}

function getRequestPath(): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($uri, PHP_URL_PATH);

    if (!is_string($path) || $path === '') {
        return '/';
    }

    return normalizeRequestPath($path);
}

function getRequestMethod(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function getRequestData(): array
{
    $rawBody = file_get_contents('php://input');

    if (is_string($rawBody) && trim($rawBody) !== '') {
        $decoded = json_decode($rawBody, true);

        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return $_POST;
}

function getDatabaseUrl(): string
{
    $databaseUrl = $_ENV['DATABASE_URL'] ?? $_SERVER['DATABASE_URL'] ?? getenv('DATABASE_URL');

    return is_string($databaseUrl) ? $databaseUrl : '';
}

function createDatabasePdo(): PDO
{
    $databaseUrl = getDatabaseUrl();

    if ($databaseUrl === '') {
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
    $db = isset($parts['path']) ? ltrim($parts['path'], '/') : '';

    parse_str($parts['query'] ?? '', $queryParams);

    $sslmode = $queryParams['sslmode'] ?? 'require';

    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
        $host,
        $port,
        $db,
        $sslmode
    );

    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function respondJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_PRETTY_PRINT);
}

function getRawPasswordMatch(string $storedPassword, string $inputPassword): bool
{
    $passwordInfo = password_get_info($storedPassword);

    if (($passwordInfo['algo'] ?? 0) === 0) {
        return hash_equals($storedPassword, $inputPassword);
    }

    return password_verify($inputPassword, $storedPassword);
}

function loginUser(object $pdo, array $input): array
{
    $email = trim((string) ($input['email'] ?? ''));
    $password = (string) ($input['password'] ?? '');

    if ($email === '' || $password === '') {
        return [
            'success' => false,
            'message' => 'email and password are required.',
        ];
    }

    $stmt = $pdo->prepare(
        'SELECT user_id, name, email, password
         FROM user_demo
         WHERE email = :email
         LIMIT 1'
    );
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !is_array($user)) {
        return [
            'success' => false,
            'message' => 'incorrect email or password',
            'hint' => 'not account yet? go to /signup',
        ];
    }

    $storedPassword = (string) ($user['password'] ?? '');

    if (!getRawPasswordMatch($storedPassword, $password)) {
        return [
            'success' => false,
            'message' => 'incorrect email or password',
            'hint' => 'not account yet? go to /signup',
        ];
    }

    return [
        'success' => true,
        'message' => 'login successfully',
    ];
}

function signupUser(object $pdo, array $input): array
{
    $name = trim((string) ($input['name'] ?? ''));
    $email = trim((string) ($input['email'] ?? ''));
    $password = (string) ($input['password'] ?? '');

    if ($name === '' || $email === '' || $password === '') {
        return [
            'success' => false,
            'message' => 'name, email, and password are required.',
        ];
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    if ($hashedPassword === false) {
        throw new RuntimeException('Unable to hash password.');
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO user_demo (name, email, password)
             VALUES (:name, :email, :password)'
        );
        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':password' => $hashedPassword,
        ]);
    } catch (PDOException $e) {
        $sqlState = (string) ($e->errorInfo[0] ?? $e->getCode());
        $errorMessage = strtolower($e->getMessage());

        if ($sqlState === '23505' || $sqlState === '23000' || str_contains($errorMessage, 'unique constraint')) {
            return [
                'success' => false,
                'message' => 'email is already existing.',
            ];
        }

        throw $e;
    }

    return [
        'success' => true,
        'message' => 'signup successfully',
    ];
}

function handleRoot(object $pdo): array
{
    $stmt = $pdo->query('SELECT NOW() AS current_time');
    $result = $stmt->fetch();

    return [
        'success' => true,
        'message' => 'Connected to Supabase successfully.',
        'server_time' => is_array($result) ? ($result['current_time'] ?? null) : null,
    ];
}

function routeRequest(): void
{
    try {
        $pdo = createDatabasePdo();
        $path = getRequestPath();
        $method = getRequestMethod();

        if ($path === '/login') {
            if ($method !== 'POST') {
                header('Allow: POST');
                respondJson([
                    'success' => false,
                    'message' => 'Method not allowed.',
                ], 405);
                return;
            }

            $result = loginUser($pdo, getRequestData());
            respondJson($result, $result['success'] ? 200 : 401);
            return;
        }

        if ($path === '/signup') {
            if ($method !== 'POST') {
                header('Allow: POST');
                respondJson([
                    'success' => false,
                    'message' => 'Method not allowed.',
                ], 405);
                return;
            }

            $result = signupUser($pdo, getRequestData());
            respondJson($result, $result['success'] ? 201 : 409);
            return;
        }

        respondJson(handleRoot($pdo));
    } catch (Throwable $e) {
        respondJson([
            'success' => false,
            'error' => $e->getMessage(),
        ], 500);
    }
}
