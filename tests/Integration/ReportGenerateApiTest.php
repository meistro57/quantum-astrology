<?php
declare(strict_types=1);

namespace QuantumAstrology\Tests\Integration;

use PHPUnit\Framework\TestCase;
use QuantumAstrology\Charts\Chart;
use QuantumAstrology\Core\User;
use QuantumAstrology\Database\Connection;

final class ReportGenerateApiTest extends TestCase
{
    /** @runInSeparateProcess */
    public function testReturnsCanonicalEnvelopeWithoutDuplicatedTopLevelFields(): void
    {
        [$dbPath, $chartId, $ownerId] = $this->bootstrapDbWithChart();

        try {
            [$status, $output] = $this->runEndpoint($dbPath, [
                'chart_id' => $chartId,
                'report_type' => 'natal',
                'format' => 'pdf',
            ], [
                'logged_in' => true,
                'user_id' => $ownerId,
            ]);

            $this->assertSame(200, $status, $output);
            $payload = json_decode($output, true);
            $this->assertIsArray($payload, $output);

            $this->assertTrue((bool)($payload['success'] ?? false), $output);
            $this->assertArrayHasKey('data', $payload);
            $this->assertIsArray($payload['data']);
            $this->assertArrayHasKey('meta', $payload);

            $this->assertArrayHasKey('pdf_base64', $payload['data']);
            $this->assertArrayHasKey('download_url', $payload['data']);
            $this->assertArrayHasKey('history_id', $payload['data']);

            // Ensure generate API does not duplicate payload keys at top-level.
            $this->assertArrayNotHasKey('pdf_base64', $payload);
            $this->assertArrayNotHasKey('download_url', $payload);
            $this->assertArrayNotHasKey('history_id', $payload);
            $this->assertArrayNotHasKey('chart_id', $payload);
            $this->assertArrayNotHasKey('report_type', $payload);
        } finally {
            @unlink($dbPath);
        }
    }

    private function bootstrapDbOnly(): string
    {
        $dbPath = tempnam(sys_get_temp_dir(), 'qa_report_generate_');
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

    private function bootstrapDbWithChart(): array
    {
        $dbPath = $this->bootstrapDbOnly();

        $uniq = bin2hex(random_bytes(4));
        $user = User::create([
            'username' => "report_generate_user_{$uniq}",
            'email' => "report_generate_user_{$uniq}@example.com",
            'password' => 'StrongPass123!',
            'timezone' => 'UTC',
        ]);
        $this->assertInstanceOf(User::class, $user);
        $userId = (int)$user->getId();

        $chart = Chart::create([
            'user_id' => $userId,
            'name' => 'Report Generate Fixture',
            'birth_datetime' => '1990-01-01 12:00:00',
            'birth_timezone' => 'UTC',
            'birth_latitude' => 40.7128,
            'birth_longitude' => -74.0060,
            'house_system' => 'P',
            'planetary_positions' => [],
            'house_positions' => [],
            'aspects' => [],
            'is_public' => 0,
        ]);
        $this->assertNotNull($chart);

        return [$dbPath, (int)$chart->getId(), $userId];
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
require %CONFIG_PATH%;
$_GET = [];
$_POST = [];
$_SERVER = array_merge($_SERVER, [
    'REQUEST_METHOD' => 'POST',
    'REQUEST_URI' => '/api/reports/generate.php',
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
            '%CONFIG_PATH%' => var_export($this->projectPath('config.php'), true),
            '%SESSION_ID%' => var_export($sessionId, true),
            '%SESSION_VARS%' => var_export($session, true),
            '%RAW_BODY%' => var_export(json_encode($payload), true),
            '%ENTRYPOINT_PATH%' => var_export($this->projectPath('api/reports/generate.php'), true),
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
