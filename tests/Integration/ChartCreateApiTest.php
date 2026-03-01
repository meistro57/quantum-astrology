<?php
declare(strict_types=1);

namespace QuantumAstrology\Tests\Integration;

use PHPUnit\Framework\TestCase;
use QuantumAstrology\Core\DB;
use QuantumAstrology\Core\User;
use QuantumAstrology\Database\Connection;

final class ChartCreateApiTest extends TestCase
{
    /** @runInSeparateProcess */
    public function testCreateRejectsUnauthenticatedRequest(): void
    {
        $dbPath = $this->bootstrapDb();

        try {
            [$status, $output] = $this->runCreateRequest($dbPath, [
                'name' => 'Test Chart',
                'birth_date' => '1990-01-01',
                'birth_time' => '12:00',
                'birth_timezone' => 'UTC',
                'birth_latitude' => 40.7128,
                'birth_longitude' => -74.0060,
            ], []);

            $this->assertSame(401, $status, $output);
            $payload = json_decode($output, true);
            $this->assertIsArray($payload, $output);
            $this->assertFalse($payload['ok'] ?? true);
            $this->assertSame('UNAUTHENTICATED', $payload['error']['code'] ?? null);
        } finally {
            @unlink($dbPath);
        }
    }

    /** @runInSeparateProcess */
    public function testCreateRejectsInvalidJson(): void
    {
        [$dbPath, $userId] = $this->bootstrapDbWithUser();

        try {
            [$status, $output] = $this->runCreateRequestRaw($dbPath, '{bad-json', [
                'logged_in' => true,
                'user_id' => $userId,
            ]);

            $this->assertSame(400, $status, $output);
            $payload = json_decode($output, true);
            $this->assertIsArray($payload, $output);
            $this->assertFalse($payload['ok'] ?? true);
            $this->assertSame('INVALID_JSON', $payload['error']['code'] ?? null);
        } finally {
            @unlink($dbPath);
        }
    }

    /** @runInSeparateProcess */
    public function testCreateRejectsMissingRequiredFields(): void
    {
        [$dbPath, $userId] = $this->bootstrapDbWithUser();

        try {
            [$status, $output] = $this->runCreateRequest($dbPath, [], [
                'logged_in' => true,
                'user_id' => $userId,
            ]);

            $this->assertSame(422, $status, $output);
            $payload = json_decode($output, true);
            $this->assertIsArray($payload, $output);
            $this->assertFalse($payload['ok'] ?? true);
            $this->assertSame('VALIDATION_ERROR', $payload['error']['code'] ?? null);
            $this->assertArrayHasKey('name', $payload['error']['fields'] ?? []);
            $this->assertArrayHasKey('birth_date', $payload['error']['fields'] ?? []);
            $this->assertArrayHasKey('birth_time', $payload['error']['fields'] ?? []);
            $this->assertArrayHasKey('birth_latitude', $payload['error']['fields'] ?? []);
            $this->assertArrayHasKey('birth_longitude', $payload['error']['fields'] ?? []);
        } finally {
            @unlink($dbPath);
        }
    }

    /** @runInSeparateProcess */
    public function testCreateRejectsInvalidCoordinates(): void
    {
        [$dbPath, $userId] = $this->bootstrapDbWithUser();

        try {
            [$status, $output] = $this->runCreateRequest($dbPath, [
                'name' => 'Bad Coords',
                'birth_date' => '1990-01-01',
                'birth_time' => '12:00',
                'birth_timezone' => 'UTC',
                'birth_latitude' => 123.45,
                'birth_longitude' => -74.0060,
            ], [
                'logged_in' => true,
                'user_id' => $userId,
            ]);

            $this->assertSame(422, $status, $output);
            $payload = json_decode($output, true);
            $this->assertIsArray($payload, $output);
            $this->assertFalse($payload['ok'] ?? true);
            $this->assertSame('VALIDATION_ERROR', $payload['error']['code'] ?? null);
            $this->assertArrayHasKey('birth_latitude', $payload['error']['fields'] ?? []);
        } finally {
            @unlink($dbPath);
        }
    }

    /** @runInSeparateProcess */
    public function testCreateSucceedsWithValidPayload(): void
    {
        [$dbPath, $userId] = $this->bootstrapDbWithUser();

        try {
            [$status, $output] = $this->runCreateRequest($dbPath, [
                'name' => 'Integration Create',
                'birth_date' => '1990-01-01',
                'birth_time' => '12:00',
                'birth_timezone' => 'UTC',
                'birth_latitude' => 40.7128,
                'birth_longitude' => -74.0060,
                'house_system' => 'P',
            ], [
                'logged_in' => true,
                'user_id' => $userId,
            ]);

            $this->assertSame(201, $status, $output);
            $payload = json_decode($output, true);
            $this->assertIsArray($payload, $output);
            $this->assertTrue($payload['ok'] ?? false);
            $this->assertSame('Integration Create', $payload['chart']['name'] ?? null);
            $this->assertGreaterThan(0, (int) ($payload['chart']['id'] ?? 0));

            $count = (int) DB::conn()->query('SELECT COUNT(*) FROM charts')->fetchColumn();
            $this->assertSame(1, $count);
        } finally {
            @unlink($dbPath);
        }
    }

    private function bootstrapDb(): string
    {
        $dbPath = tempnam(sys_get_temp_dir(), 'qa_create_');
        if ($dbPath === false) {
            $this->fail('Failed to allocate a temporary SQLite file.');
        }

        putenv('DB_DRIVER=sqlite');
        putenv("DB_SQLITE_PATH={$dbPath}");
        $_ENV['DB_DRIVER'] = 'sqlite';
        $_SERVER['DB_DRIVER'] = 'sqlite';
        $_ENV['DB_SQLITE_PATH'] = $dbPath;
        $_SERVER['DB_SQLITE_PATH'] = $dbPath;

        require_once $this->projectPath('config.php');
        Connection::getInstance();

        return $dbPath;
    }

    private function bootstrapDbWithUser(): array
    {
        $dbPath = $this->bootstrapDb();

        $uniq = bin2hex(random_bytes(4));
        $user = User::create([
            'username' => "create_user_{$uniq}",
            'email' => "create_user_{$uniq}@example.com",
            'password' => 'StrongPass123!',
            'timezone' => 'UTC',
        ]);
        $this->assertInstanceOf(User::class, $user);

        return [$dbPath, (int) $user->getId()];
    }

    private function runCreateRequest(string $dbPath, array $payload, array $session): array
    {
        return $this->runCreateRequestRaw($dbPath, json_encode($payload), $session);
    }

    private function runCreateRequestRaw(string $dbPath, string $rawBody, array $session): array
    {
        $runnerPath = tempnam(sys_get_temp_dir(), 'qa_runner_');
        if ($runnerPath === false) {
            $this->fail('Failed to create a temporary runner script.');
        }

        $sessionId = 'qa-' . bin2hex(random_bytes(6));

        $bootstrap = <<<'PHP'
<?php
putenv('DB_DRIVER=sqlite');
putenv('DB_SQLITE_PATH=%DB_PATH%');
$_ENV['DB_DRIVER'] = 'sqlite';
$_SERVER['DB_DRIVER'] = 'sqlite';
$_ENV['DB_SQLITE_PATH'] = '%DB_PATH%';
$_SERVER['DB_SQLITE_PATH'] = '%DB_PATH%';
$_GET = [];
$_POST = [];
$_SERVER = array_merge($_SERVER, %SERVER_VARS%);
session_id(%SESSION_ID%);
session_start();
$_SESSION = %SESSION_VARS%;
session_write_close();
$_COOKIE[session_name()] = %SESSION_ID%;
$GLOBALS['__qa_body'] = %RAW_BODY%;
class QaPhpInputStream {
    /** @var resource|null */
    public $context;
    private int $position = 0;
    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool {
        $this->position = 0;
        return true;
    }
    public function stream_read(int $count): string {
        $chunk = substr((string) $GLOBALS['__qa_body'], $this->position, $count);
        $this->position += strlen($chunk);
        return $chunk;
    }
    public function stream_eof(): bool {
        return $this->position >= strlen((string) $GLOBALS['__qa_body']);
    }
    public function stream_stat(): array { return []; }
}
stream_wrapper_unregister('php');
stream_wrapper_register('php', QaPhpInputStream::class);
register_shutdown_function(static function (): void {
    if (in_array('php', stream_get_wrappers(), true)) {
        stream_wrapper_restore('php');
    }
    $status = http_response_code();
    echo "\n__STATUS__" . ($status === false ? 200 : $status);
});
require %ENTRYPOINT_PATH%;
PHP;

        $code = strtr($bootstrap, [
            '%DB_PATH%' => addslashes($dbPath),
            '%SERVER_VARS%' => var_export([
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/api/chart_create.php',
                'HTTP_CONTENT_TYPE' => 'application/json',
            ], true),
            '%SESSION_ID%' => var_export($sessionId, true),
            '%SESSION_VARS%' => var_export($session, true),
            '%RAW_BODY%' => var_export($rawBody, true),
            '%ENTRYPOINT_PATH%' => var_export($this->projectPath('api/chart_create.php'), true),
        ]);

        file_put_contents($runnerPath, $code);

        $output = [];
        $exitCode = 0;
        exec('php ' . escapeshellarg($runnerPath), $output, $exitCode);
        @unlink($runnerPath);

        $rawOutput = implode("\n", $output);
        $statusPos = strrpos($rawOutput, '__STATUS__');
        $this->assertNotFalse($statusPos, "Missing status marker in output:\n{$rawOutput}");

        $body = trim(substr($rawOutput, 0, (int) $statusPos));
        $status = (int) trim(substr($rawOutput, (int) $statusPos + 10));

        $this->assertSame(0, $exitCode, "Runner exited with code {$exitCode}. Output:\n{$rawOutput}");

        return [$status, $body];
    }

    private function projectPath(string $relativePath): string
    {
        return dirname(__DIR__, 2) . '/' . ltrim($relativePath, '/');
    }
}
