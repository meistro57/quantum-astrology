<?php
declare(strict_types=1);

namespace QuantumAstrology\Tests\Integration;

use PHPUnit\Framework\TestCase;
use QuantumAstrology\Database\Connection;

final class AdminRedisDashboardApiTest extends TestCase
{
    /** @runInSeparateProcess */
    public function testAdminRedisDashboardActionReturnsStructuredPayload(): void
    {
        $dbPath = $this->bootstrapDbWithAdminUser();
        $csrf = 'csrf-admin-redis-test';

        try {
            [$status, $output] = $this->runEndpoint($dbPath, [
                'action' => 'get_redis_dashboard',
                'csrf' => $csrf,
            ], [
                'logged_in' => true,
                'user_id' => 1,
                'csrf_token' => $csrf,
            ]);

            $this->assertSame(200, $status, $output);
            $payload = json_decode($output, true);
            $this->assertIsArray($payload, $output);
            $this->assertTrue((bool)($payload['ok'] ?? false), $output);
            $this->assertSame('get_redis_dashboard', $payload['action'] ?? null);

            $this->assertContains($payload['status'] ?? null, ['ok', 'unavailable', 'error']);
            $this->assertArrayHasKey('driver', $payload);
            $this->assertArrayHasKey('message', $payload);
            $this->assertArrayHasKey('connection', $payload);
            $this->assertIsArray($payload['connection']);
            $this->assertArrayHasKey('host', $payload['connection']);
            $this->assertArrayHasKey('port', $payload['connection']);
            $this->assertArrayHasKey('db', $payload['connection']);
            $this->assertArrayHasKey('fetched_at', $payload);
        } finally {
            @unlink($dbPath);
        }
    }

    private function bootstrapDbWithAdminUser(): string
    {
        $dbPath = tempnam(sys_get_temp_dir(), 'qa_admin_redis_');
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

        $pdo = Connection::getInstance();
        $stmt = $pdo->query('SELECT id FROM users WHERE id = 1');
        $seededAdminId = $stmt ? (int)$stmt->fetchColumn() : 0;
        $this->assertSame(1, $seededAdminId, 'Expected seeded admin user id=1 to exist.');

        return $dbPath;
    }

    private function runEndpoint(string $dbPath, array $payload, array $session): array
    {
        $runnerPath = tempnam(sys_get_temp_dir(), 'qa_runner_');
        if ($runnerPath === false) {
            $this->fail('Failed to create temporary runner.');
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
$_SERVER = array_merge($_SERVER, [
    'REQUEST_METHOD' => 'POST',
    'REQUEST_URI' => '/api/admin/actions',
    'HTTP_CONTENT_TYPE' => 'application/json',
]);
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
        $chunk = substr((string)$GLOBALS['__qa_body'], $this->position, $count);
        $this->position += strlen($chunk);
        return $chunk;
    }
    public function stream_eof(): bool {
        return $this->position >= strlen((string)$GLOBALS['__qa_body']);
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
            '%SESSION_ID%' => var_export($sessionId, true),
            '%SESSION_VARS%' => var_export($session, true),
            '%RAW_BODY%' => var_export(json_encode($payload), true),
            '%ENTRYPOINT_PATH%' => var_export($this->projectPath('index.php'), true),
        ]);

        file_put_contents($runnerPath, $code);

        $output = [];
        $exitCode = 0;
        exec('php ' . escapeshellarg($runnerPath), $output, $exitCode);
        @unlink($runnerPath);

        $rawOutput = implode("\n", $output);
        $statusPos = strrpos($rawOutput, '__STATUS__');
        $this->assertNotFalse($statusPos, "Missing status marker:\n{$rawOutput}");

        $body = trim(substr($rawOutput, 0, (int)$statusPos));
        $status = (int)trim(substr($rawOutput, (int)$statusPos + 10));

        $this->assertSame(0, $exitCode, "Runner exited with code {$exitCode}. Output:\n{$rawOutput}");

        return [$status, $body];
    }

    private function projectPath(string $relativePath): string
    {
        return dirname(__DIR__, 2) . '/' . ltrim($relativePath, '/');
    }
}
