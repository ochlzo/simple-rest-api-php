<?php

declare(strict_types=1);

require __DIR__ . '/../app.php';

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
    }
}

function assertTrueValue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

final class FakeDatabase
{
    /**
     * @var array<int, array{user_id:int, name:string, email:string, password:string}>
     */
    public array $users = [];

    private int $nextUserId = 1;

    public function prepare(string $sql): FakeStatement
    {
        return new FakeStatement($this, $sql);
    }

    public function query(string $sql): FakeStatement
    {
        return new FakeStatement($this, $sql, true);
    }

    public function insertUser(string $name, string $email, string $password): void
    {
        foreach ($this->users as $user) {
            if ($user['email'] === $email) {
                throw new PDOException('UNIQUE constraint failed: user_demo.email', 23000);
            }
        }

        $this->users[] = [
            'user_id' => $this->nextUserId++,
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ];
    }

    public function findUserByEmail(string $email): array|false
    {
        foreach ($this->users as $user) {
            if ($user['email'] === $email) {
                return $user;
            }
        }

        return false;
    }
}

final class FakeStatement
{
    private mixed $result = false;

    public function __construct(
        private FakeDatabase $database,
        private string $sql,
        private bool $isQuery = false,
    ) {
    }

    public function execute(array $params = []): void
    {
        if (str_contains($this->sql, 'FROM user_demo') && str_contains($this->sql, 'WHERE email = :email')) {
            $email = (string) ($params[':email'] ?? $params['email'] ?? '');
            $this->result = $this->database->findUserByEmail($email);
            return;
        }

        if (str_contains($this->sql, 'INSERT INTO user_demo')) {
            $name = (string) ($params[':name'] ?? $params['name'] ?? '');
            $email = (string) ($params[':email'] ?? $params['email'] ?? '');
            $password = (string) ($params[':password'] ?? $params['password'] ?? '');
            $this->database->insertUser($name, $email, $password);
            $this->result = true;
            return;
        }

        $this->result = true;
    }

    public function fetch(): array|false
    {
        return is_array($this->result) ? $this->result : false;
    }

    public function fetchColumn(): mixed
    {
        if (!is_array($this->result)) {
            return false;
        }

        return array_values($this->result)[0] ?? false;
    }
}

function runTests(): void
{
    $pdo = new FakeDatabase();

    $signup = signupUser($pdo, [
        'name' => 'Ada',
        'email' => 'ada@example.com',
        'password' => 'secret123',
    ]);

    assertTrueValue($signup['success'], 'Signup should succeed for a new user.');
    assertSameValue('signup successfully', $signup['message'], 'Signup success message should match.');

    $storedPassword = $pdo->users[0]['password'] ?? '';
    assertTrueValue(password_get_info((string) $storedPassword)['algo'] !== 0, 'Password should be hashed before storage.');

    $duplicate = signupUser($pdo, [
        'name' => 'Ada 2',
        'email' => 'ada@example.com',
        'password' => 'another-secret',
    ]);

    assertTrueValue(!$duplicate['success'], 'Signup should fail for duplicate email.');
    assertSameValue('email is already existing.', $duplicate['message'], 'Duplicate email message should match.');

    $login = loginUser($pdo, [
        'email' => 'ada@example.com',
        'password' => 'secret123',
    ]);

    assertTrueValue($login['success'], 'Login should succeed with matching credentials.');
    assertSameValue('login successfully', $login['message'], 'Login success message should match.');

    $wrongLogin = loginUser($pdo, [
        'email' => 'ada@example.com',
        'password' => 'wrong-password',
    ]);

    assertTrueValue(!$wrongLogin['success'], 'Login should fail with the wrong password.');
    assertSameValue('incorrect email or password', $wrongLogin['message'], 'Login failure message should match.');
    assertSameValue('not account yet? go to /signup', $wrongLogin['hint'], 'Signup hint should match.');

    $missingLogin = loginUser($pdo, [
        'email' => '',
        'password' => '',
    ]);

    assertTrueValue(!$missingLogin['success'], 'Login should fail when required fields are missing.');
    assertSameValue('email and password are required.', $missingLogin['message'], 'Missing login fields message should match.');

    echo "All endpoint tests passed.\n";
}

runTests();
