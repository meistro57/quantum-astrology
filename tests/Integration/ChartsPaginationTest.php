<?php
declare(strict_types=1);

namespace QuantumAstrology\Tests\Integration;

use PHPUnit\Framework\TestCase;
use QuantumAstrology\Charts\Chart;
use QuantumAstrology\Core\User;
use QuantumAstrology\Database\Connection;

final class ChartsPaginationTest extends TestCase
{
    /** @runInSeparateProcess */
    public function testChartsListApiReturnsPaginationMetadata(): void
    {
        [$dbPath, $userId] = $this->bootstrapFixture(45);

        try {
            [$status, $output] = $this->runPhpEntrypoint(
                $this->projectPath('api/charts_list.php'),
                $dbPath,
                ['limit' => '10', 'offset' => '20'],
                [
                    'REQUEST_METHOD' => 'GET',
                    'REQUEST_URI' => '/api/charts_list.php?limit=10&offset=20',
                ],
                [
                    'logged_in' => true,
                    'user_id' => $userId,
                    'csrf_token' => 'test-token',
                ]
            );

            $this->assertSame(200, $status, $output);

            $payload = json_decode($output, true);
            $this->assertIsArray($payload, $output);
            $this->assertTrue($payload['ok'] ?? false);
            $this->assertCount(10, $payload['charts'] ?? []);
            $this->assertSame(10, $payload['pagination']['limit'] ?? null);
            $this->assertSame(20, $payload['pagination']['offset'] ?? null);
            $this->assertSame(45, $payload['pagination']['total'] ?? null);
            $this->assertSame(10, $payload['pagination']['count'] ?? null);
            $this->assertTrue($payload['pagination']['has_more'] ?? false);
            $this->assertSame(30, $payload['pagination']['next_offset'] ?? null);
            $this->assertSame('Chart 25', $payload['charts'][0]['name'] ?? null);
        } finally {
            @unlink($dbPath);
        }
    }

    /** @runInSeparateProcess */
    public function testChartsPageClampsOutOfRangePage(): void
    {
        [$dbPath, $userId] = $this->bootstrapFixture(45);

        try {
            [$status, $html] = $this->runPhpEntrypoint(
                $this->projectPath('pages/charts/index.php'),
                $dbPath,
                ['page' => '999'],
                [
                    'REQUEST_METHOD' => 'GET',
                    'REQUEST_URI' => '/charts?page=999',
                ],
                [
                    'logged_in' => true,
                    'user_id' => $userId,
                    'csrf_token' => 'test-token',
                ]
            );

            $this->assertSame(200, $status, $html);
            $this->assertStringContainsString('Page 3 of 3', $html);
            $this->assertStringContainsString('Showing 41-45 of 45 charts', $html);
            $this->assertStringContainsString('Chart 5', $html);
            $this->assertStringNotContainsString('No Charts Yet', $html);
        } finally {
            @unlink($dbPath);
        }
    }

    /** @runInSeparateProcess */
    public function testChartsListApiAppliesSearchVisibilityAndSort(): void
    {
        [$dbPath, $userId] = $this->bootstrapFixture(45);

        try {
            [$status, $output] = $this->runPhpEntrypoint(
                $this->projectPath('api/charts_list.php'),
                $dbPath,
                [
                    'limit' => '5',
                    'offset' => '0',
                    'visibility' => 'public',
                    'sort' => 'oldest',
                    'q' => 'Chart',
                ],
                [
                    'REQUEST_METHOD' => 'GET',
                    'REQUEST_URI' => '/api/charts_list.php?limit=5&offset=0&visibility=public&sort=oldest&q=Chart',
                ],
                [
                    'logged_in' => true,
                    'user_id' => $userId,
                    'csrf_token' => 'test-token',
                ]
            );

            $this->assertSame(200, $status, $output);
            $payload = json_decode($output, true);
            $this->assertIsArray($payload, $output);
            $this->assertTrue($payload['ok'] ?? false);
            $this->assertCount(5, $payload['charts'] ?? []);
            $this->assertSame('Chart 2', $payload['charts'][0]['name'] ?? null);
            $this->assertSame('public', $payload['filters']['visibility'] ?? null);
            $this->assertSame('oldest', $payload['filters']['sort'] ?? null);
            $this->assertSame('Chart', $payload['filters']['q'] ?? null);
            $this->assertSame(22, $payload['pagination']['total'] ?? null);
        } finally {
            @unlink($dbPath);
        }
    }

    private function bootstrapFixture(int $chartCount): array
    {
        $dbPath = tempnam(sys_get_temp_dir(), 'qa_charts_');
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

        $user = User::create([
            'username' => 'pagination_user',
            'email' => 'pagination_user@example.com',
            'password' => 'StrongPass123!',
            'first_name' => 'Pagination',
            'last_name' => 'Tester',
            'timezone' => 'UTC',
        ]);

        $this->assertInstanceOf(User::class, $user);
        $userId = (int) $user->getId();

        for ($i = 1; $i <= $chartCount; $i++) {
            $chart = Chart::create([
                'user_id' => $userId,
                'name' => "Chart {$i}",
                'birth_datetime' => '1990-01-01 12:00:00',
                'birth_timezone' => 'UTC',
                'birth_latitude' => 40.7128,
                'birth_longitude' => -74.0060,
                'house_system' => 'P',
                'planetary_positions' => [],
                'house_positions' => [],
                'aspects' => [],
                'is_public' => $i % 2 === 0 ? 1 : 0,
            ]);
            $this->assertNotNull($chart, "Failed to create fixture chart {$i}.");
        }

        return [$dbPath, $userId];
    }

    private function runPhpEntrypoint(
        string $entrypointPath,
        string $dbPath,
        array $get,
        array $server,
        array $session
    ): array {
        $runnerPath = tempnam(sys_get_temp_dir(), 'qa_runner_');
        if ($runnerPath === false) {
            $this->fail('Failed to create a temporary runner script.');
        }

        $sessionId = 'qa_' . bin2hex(random_bytes(6));
        $bootstrap = <<<'PHP'
<?php
putenv('DB_DRIVER=sqlite');
putenv('DB_SQLITE_PATH=%DB_PATH%');
$_ENV['DB_DRIVER'] = 'sqlite';
$_SERVER['DB_DRIVER'] = 'sqlite';
$_ENV['DB_SQLITE_PATH'] = '%DB_PATH%';
$_SERVER['DB_SQLITE_PATH'] = '%DB_PATH%';
$_GET = %GET_VARS%;
$_POST = [];
$_SERVER = array_merge($_SERVER, %SERVER_VARS%);
session_id(%SESSION_ID%);
session_start();
$_SESSION = %SESSION_VARS%;
session_write_close();
$_COOKIE[session_name()] = %SESSION_ID%;
register_shutdown_function(static function (): void {
    $status = http_response_code();
    echo "\n__STATUS__" . ($status === false ? 200 : $status);
});
require %ENTRYPOINT_PATH%;
PHP;

        $code = strtr($bootstrap, [
            '%DB_PATH%' => addslashes($dbPath),
            '%GET_VARS%' => var_export($get, true),
            '%SERVER_VARS%' => var_export($server, true),
            '%SESSION_ID%' => var_export(str_replace('_', '-', $sessionId), true),
            '%SESSION_VARS%' => var_export($session, true),
            '%ENTRYPOINT_PATH%' => var_export($entrypointPath, true),
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
