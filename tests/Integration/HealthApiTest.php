<?php
declare(strict_types=1);

namespace QuantumAstrology\Tests\Integration;

use PHPUnit\Framework\TestCase;
use QuantumAstrology\Database\Connection;

final class HealthApiTest extends TestCase
{
    /** @runInSeparateProcess */
    public function testHealthPhpEndpointTreatsMissingSwetestAsWarning(): void
    {
        $dbPath = $this->bootstrapSqliteDb();

        try {
            [$status, $body] = $this->runEntrypoint(
                $this->projectPath('api/health.php'),
                '/api/health.php',
                $dbPath
            );

            $this->assertSame(200, $status, $body);
            $payload = json_decode($body, true);
            $this->assertIsArray($payload, $body);
            $this->assertTrue((bool)($payload['ok'] ?? false), $body);
            $this->assertSame('ok', $payload['db']['status'] ?? null, $body);
            $this->assertContains($payload['swetest']['status'] ?? null, ['ok', 'warn'], $body);
        } finally {
            @unlink($dbPath);
        }
    }

    /** @runInSeparateProcess */
    public function testHealthRouteUsesFullHealthPayload(): void
    {
        $dbPath = $this->bootstrapSqliteDb();

        try {
            [$status, $body] = $this->runEntrypoint(
                $this->projectPath('index.php'),
                '/api/health',
                $dbPath
            );

            $this->assertSame(200, $status, $body);
            $payload = json_decode($body, true);
            $this->assertIsArray($payload, $body);
            $this->assertArrayHasKey('db', $payload, $body);
            $this->assertArrayHasKey('swetest', $payload, $body);
            $this->assertArrayHasKey('warnings', $payload, $body);
            $this->assertSame('ok', $payload['db']['status'] ?? null, $body);
        } finally {
            @unlink($dbPath);
        }
    }

    private function bootstrapSqliteDb(): string
    {
        $dbPath = tempnam(sys_get_temp_dir(), 'qa_health_');
        if ($dbPath === false) {
            $this->fail('Failed to allocate temporary sqlite DB path.');
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

    private function runEntrypoint(string $entrypointPath, string $requestUri, string $dbPath): array
    {
        $runnerPath = tempnam(sys_get_temp_dir(), 'qa_runner_');
        if ($runnerPath === false) {
            $this->fail('Failed to create temporary runner.');
        }

        $bootstrap = <<<'PHP'
<?php
putenv('DB_DRIVER=sqlite');
putenv('DB_SQLITE_PATH=%DB_PATH%');
$_ENV['DB_DRIVER'] = 'sqlite';
$_SERVER['DB_DRIVER'] = 'sqlite';
$_ENV['DB_SQLITE_PATH'] = '%DB_PATH%';
$_SERVER['DB_SQLITE_PATH'] = '%DB_PATH%';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = %REQUEST_URI%;
$_GET = [];
$_POST = [];
register_shutdown_function(static function (): void {
    $status = http_response_code();
    echo "\n__STATUS__" . ($status === false ? 200 : $status);
});
require %ENTRYPOINT_PATH%;
PHP;

        $code = strtr($bootstrap, [
            '%DB_PATH%' => addslashes($dbPath),
            '%REQUEST_URI%' => var_export($requestUri, true),
            '%ENTRYPOINT_PATH%' => var_export($entrypointPath, true),
        ]);

        file_put_contents($runnerPath, $code);

        $output = [];
        $exitCode = 0;
        exec('php ' . escapeshellarg($runnerPath), $output, $exitCode);
        @unlink($runnerPath);

        $rawOutput = implode("\n", $output);
        $statusPos = strrpos($rawOutput, '__STATUS__');
        $this->assertNotFalse($statusPos, "Missing status marker:\n{$rawOutput}");
        $this->assertSame(0, $exitCode, "Runner failed with exit code {$exitCode}:\n{$rawOutput}");

        $body = trim(substr($rawOutput, 0, (int)$statusPos));
        $status = (int) trim(substr($rawOutput, (int)$statusPos + 10));

        return [$status, $body];
    }

    private function projectPath(string $relativePath): string
    {
        return dirname(__DIR__, 2) . '/' . ltrim($relativePath, '/');
    }
}

