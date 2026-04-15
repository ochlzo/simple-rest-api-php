<?php

declare(strict_types=1);

function loadEnv(string $path): void
{
    if (!file_exists($path)) {
        throw new RuntimeException(".env file not found at: {$path}");
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines === false) {
        throw new RuntimeException('Unable to read .env file.');
    }

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');

        $key = trim($key);
        $value = trim(trim($value), "\"'");

        $_ENV[$key] = $value;
        putenv("{$key}={$value}");
    }
}

function normalizeRequestPath(string $path): string
{
    $path = rtrim($path, '/');

    return $path === '' ? '/' : $path;
}

function getRequestPath(): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($uri, PHP_URL_PATH);

    return is_string($path) && $path !== '' ? normalizeRequestPath($path) : '/';
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

function getRequestInput(): array
{
    return array_merge($_GET, getRequestData());
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

    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
        $host,
        $port,
        $db,
        $queryParams['sslmode'] ?? 'require'
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

function loginUser(PDO $pdo, array $input): array
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
            'hint' => 'no account yet? go to /signup',
        ];
    }

    $storedPassword = (string) ($user['password'] ?? '');

    if (!getRawPasswordMatch($storedPassword, $password)) {
        return [
            'success' => false,
            'message' => 'incorrect email or password',
            'hint' => 'no account yet? go to /signup',
        ];
    }

    return [
        'success' => true,
        'message' => 'login successfully',
    ];
}

function signupUser(PDO $pdo, array $input): array
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
                'error' => 'duplicate_email',
                'field' => 'email',
                'message' => 'This email is already registered. Please use a different email or log in.',
            ];
        }

        throw $e;
    }

    return [
        'success' => true,
        'message' => 'signup successfully',
    ];
}

function updateUser(PDO $pdo, array $input): array
{
    $email = trim((string) ($input['email'] ?? ''));
    $name = trim((string) ($input['name'] ?? ''));
    $newEmail = trim((string) ($input['new_email'] ?? ''));
    $password = (string) ($input['password'] ?? '');
    $updates = [];
    $params = [':email' => $email];

    if ($email === '') {
        return [
            'success' => false,
            'message' => 'email is required.',
        ];
    }

    if ($name !== '') {
        $updates[] = 'name = :name';
        $params[':name'] = $name;
    }

    if ($newEmail !== '') {
        $updates[] = 'email = :new_email';
        $params[':new_email'] = $newEmail;
    }

    if ($password !== '') {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        if ($hashedPassword === false) {
            throw new RuntimeException('Unable to hash password.');
        }

        $updates[] = 'password = :password';
        $params[':password'] = $hashedPassword;
    }

    if ($updates === []) {
        return [
            'success' => false,
            'message' => 'name, new_email, or password is required.',
        ];
    }

    $sql = 'UPDATE user_demo SET ' . implode(', ', $updates) . ' WHERE email = :email RETURNING user_id, name, email';

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $user = $stmt->fetch();
    } catch (PDOException $e) {
        $sqlState = (string) ($e->errorInfo[0] ?? $e->getCode());
        $errorMessage = strtolower($e->getMessage());

        if ($sqlState === '23505' || $sqlState === '23000' || str_contains($errorMessage, 'unique constraint')) {
            return [
                'success' => false,
                'error' => 'duplicate_email',
                'field' => 'new_email',
                'message' => 'This email is already registered. Please use a different email.',
            ];
        }

        throw $e;
    }

    if (!$user || !is_array($user)) {
        return [
            'success' => false,
            'message' => 'user not found.',
        ];
    }

    return [
        'success' => true,
        'message' => 'user updated successfully',
        'user' => $user,
    ];
}

function deleteUser(PDO $pdo, array $input): array
{
    $email = trim((string) ($input['email'] ?? ''));

    if ($email === '') {
        return [
            'success' => false,
            'message' => 'email is required.',
        ];
    }

    $stmt = $pdo->prepare('DELETE FROM user_demo WHERE email = :email RETURNING user_id, email');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !is_array($user)) {
        return [
            'success' => false,
            'message' => 'user not found.',
        ];
    }

    return [
        'success' => true,
        'message' => 'user deleted successfully',
        'user' => $user,
    ];
}

function handleRoot(PDO $pdo): array
{
    $result = $pdo->query('SELECT NOW() AS current_time')->fetch();

    return [
        'success' => true,
        'message' => 'Connected to Supabase successfully.',
        'server_time' => is_array($result) ? ($result['current_time'] ?? null) : null,
    ];
}

try {
    loadEnv(__DIR__ . '/.env');

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
        $statusCode = $result['success'] ? 200 : 401;

        respondJson($result, $statusCode);
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
        $statusCode = $result['success'] ? 201 : 409;

        respondJson($result, $statusCode);
        return;
    }

    if ($path === '/update') {
        if ($method !== 'PUT' && $method !== 'PATCH') {
            header('Allow: PUT, PATCH');
            respondJson([
                'success' => false,
                'message' => 'Method not allowed.',
            ], 405);
            return;
        }

        $result = updateUser($pdo, getRequestInput());
        $statusCode = 200;

        if (!$result['success']) {
            $statusCode = ($result['error'] ?? '') === 'duplicate_email'
                ? 409
                : (($result['message'] ?? '') === 'user not found.' ? 404 : 400);
        }

        respondJson($result, $statusCode);
        return;
    }

    if ($path === '/delete') {
        if ($method !== 'DELETE') {
            header('Allow: DELETE');
            respondJson([
                'success' => false,
                'message' => 'Method not allowed.',
            ], 405);
            return;
        }

        $result = deleteUser($pdo, getRequestInput());
        $statusCode = $result['success'] ? 200 : (($result['message'] ?? '') === 'user not found.' ? 404 : 400);

        respondJson($result, $statusCode);
        return;
    }

    if ($path === '/') {
        respondJson(handleRoot($pdo), 200);
        return;
    }

    respondJson([
        'success' => false,
        'message' => 'Route not found.',
    ], 404);
} catch (Throwable $e) {
    respondJson([
        'success' => false,
        'error' => $e->getMessage(),
    ], 500);
}
