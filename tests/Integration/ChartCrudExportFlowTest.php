<?php
declare(strict_types=1);

namespace QuantumAstrology\Tests\Integration;

use PHPUnit\Framework\TestCase;
use QuantumAstrology\Core\User;
use QuantumAstrology\Database\Connection;

final class ChartCrudExportFlowTest extends TestCase
{
    /** @runInSeparateProcess */
    public function testChartCreateListExportDeleteFlow(): void
    {
        [$dbPath, $userId] = $this->bootstrapDbWithUser();

        $session = [
            'logged_in' => true,
            'user_id' => $userId,
            'csrf_token' => 'e2e-csrf-token',
        ];

        try {
            // 1) Create chart
            [$createStatus, $createBody] = $this->runEntrypoint(
                $this->projectPath('api/chart_create.php'),
                $dbPath,
                method: 'POST',
                uri: '/api/chart_create.php',
                session: $session,
                rawBody: json_encode([
                    'name' => 'E2E Flow Chart',
                    'birth_date' => '1990-01-01',
                    'birth_time' => '12:00',
                    'birth_timezone' => 'UTC',
                    'birth_latitude' => 40.7128,
                    'birth_longitude' => -74.0060,
                    'house_system' => 'P',
                ], JSON_THROW_ON_ERROR),
                contentType: 'application/json'
            );

            $this->assertSame(201, $createStatus, $createBody);
            $createPayload = json_decode($createBody, true);
            $this->assertIsArray($createPayload, $createBody);
            $this->assertTrue($createPayload['ok'] ?? false);
            $chartId = (int) ($createPayload['chart']['id'] ?? 0);
            $this->assertGreaterThan(0, $chartId);

            // 2) List charts and verify presence
            [$listStatus, $listBody] = $this->runEntrypoint(
                $this->projectPath('api/charts_list.php'),
                $dbPath,
                method: 'GET',
                uri: '/api/charts_list.php?limit=10&offset=0',
                get: ['limit' => '10', 'offset' => '0'],
                session: $session
            );

            $this->assertSame(200, $listStatus, $listBody);
            $listPayload = json_decode($listBody, true);
            $this->assertIsArray($listPayload, $listBody);
            $this->assertTrue($listPayload['ok'] ?? false);
            $this->assertSame(1, (int) ($listPayload['pagination']['total'] ?? -1));

            $chartIds = array_map(
                static fn(array $row): int => (int) ($row['id'] ?? 0),
                is_array($listPayload['charts'] ?? null) ? $listPayload['charts'] : []
            );
            $this->assertContains($chartId, $chartIds);

            // 3) Export as JSON
            [$exportJsonStatus, $exportJsonBody] = $this->runEntrypoint(
                $this->projectPath('api/charts/export.php'),
                $dbPath,
                method: 'GET',
                uri: '/api/charts/export.php?id=' . $chartId . '&format=json',
                get: ['id' => (string) $chartId, 'format' => 'json'],
                session: $session
            );

            $this->assertSame(200, $exportJsonStatus, $exportJsonBody);
            $exportJsonPayload = json_decode($exportJsonBody, true);
            $this->assertIsArray($exportJsonPayload, $exportJsonBody);
            $this->assertTrue($exportJsonPayload['ok'] ?? false);
            $this->assertSame($chartId, (int) ($exportJsonPayload['chart']['id'] ?? 0));
            $this->assertSame('E2E Flow Chart', $exportJsonPayload['chart']['name'] ?? null);

            // 4) Export as CSV
            [$exportCsvStatus, $exportCsvBody] = $this->runEntrypoint(
                $this->projectPath('api/charts/export.php'),
                $dbPath,
                method: 'GET',
                uri: '/api/charts/export.php?id=' . $chartId . '&format=csv',
                get: ['id' => (string) $chartId, 'format' => 'csv'],
                session: $session
            );

            $this->assertSame(200, $exportCsvStatus, $exportCsvBody);
            $this->assertStringContainsString('field,value', $exportCsvBody);
            $this->assertStringContainsString('id,' . $chartId, $exportCsvBody);
            $this->assertStringContainsString('name,"E2E Flow Chart"', $exportCsvBody);

            // 5) Export as SVG redirect
            [$exportSvgStatus] = $this->runEntrypoint(
                $this->projectPath('api/charts/export.php'),
                $dbPath,
                method: 'GET',
                uri: '/api/charts/export.php?id=' . $chartId . '&format=svg',
                get: ['id' => (string) $chartId, 'format' => 'svg'],
                session: $session
            );

            $this->assertSame(302, $exportSvgStatus);

            // 6) Delete chart
            [$deleteStatus, $deleteBody] = $this->runEntrypoint(
                $this->projectPath('api/chart_delete.php'),
                $dbPath,
                method: 'POST',
                uri: '/api/chart_delete.php',
                session: $session,
                rawBody: json_encode([
                    'id' => $chartId,
                    'csrf' => 'e2e-csrf-token',
                ], JSON_THROW_ON_ERROR),
                contentType: 'application/json'
            );

            $this->assertSame(200, $deleteStatus, $deleteBody);
            $deletePayload = json_decode($deleteBody, true);
            $this->assertIsArray($deletePayload, $deleteBody);
            $this->assertTrue($deletePayload['ok'] ?? false);
            $this->assertSame($chartId, (int) ($deletePayload['deleted'] ?? 0));

            // 7) List again and verify removal
            [$listAfterStatus, $listAfterBody] = $this->runEntrypoint(
                $this->projectPath('api/charts_list.php'),
                $dbPath,
                method: 'GET',
                uri: '/api/charts_list.php?limit=10&offset=0',
                get: ['limit' => '10', 'offset' => '0'],
                session: $session
            );

            $this->assertSame(200, $listAfterStatus, $listAfterBody);
            $listAfterPayload = json_decode($listAfterBody, true);
            $this->assertIsArray($listAfterPayload, $listAfterBody);
            $this->assertTrue($listAfterPayload['ok'] ?? false);
            $this->assertSame(0, (int) ($listAfterPayload['pagination']['total'] ?? -1));
            $this->assertSame([], $listAfterPayload['charts'] ?? null);
        } finally {
            @unlink($dbPath);
        }
    }

    private function bootstrapDbWithUser(): array
    {
        $dbPath = tempnam(sys_get_temp_dir(), 'qa_e2e_');
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

        $uniq = bin2hex(random_bytes(4));
        $user = User::create([
            'username' => "e2e_user_{$uniq}",
            'email' => "e2e_user_{$uniq}@example.com",
            'password' => 'StrongPass123!',
            'timezone' => 'UTC',
        ]);

        $this->assertInstanceOf(User::class, $user);

        return [$dbPath, (int) $user->getId()];
    }

    private function runEntrypoint(
        string $entrypointPath,
        string $dbPath,
        string $method,
        string $uri,
        array $get = [],
        array $session = [],
        ?string $rawBody = null,
        ?string $contentType = null
    ): array {
        $runnerPath = tempnam(sys_get_temp_dir(), 'qa_runner_');
        if ($runnerPath === false) {
            $this->fail('Failed to create a temporary runner script.');
        }

        $sessionId = 'qa-' . bin2hex(random_bytes(6));
        $serverVars = [
            'REQUEST_METHOD' => strtoupper($method),
            'REQUEST_URI' => $uri,
        ];
        if ($contentType !== null) {
            $serverVars['HTTP_CONTENT_TYPE'] = $contentType;
            $serverVars['CONTENT_TYPE'] = $contentType;
        }

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
$GLOBALS['__qa_body'] = %RAW_BODY%;
%INPUT_WRAPPER_BOOTSTRAP%
register_shutdown_function(static function (): void {
    %INPUT_WRAPPER_RESTORE%
    $status = http_response_code();
    echo "\n__STATUS__" . ($status === false ? 200 : $status);
});
require %ENTRYPOINT_PATH%;
PHP;

        $useInputWrapper = $rawBody !== null;
        $inputWrapperBootstrap = '';
        $inputWrapperRestore = '';
        if ($useInputWrapper) {
            $inputWrapperBootstrap = <<<'PHP'
class QaPhpInputStreamE2E {
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
stream_wrapper_register('php', QaPhpInputStreamE2E::class);
PHP;
            $inputWrapperRestore = <<<'PHP'
if (in_array('php', stream_get_wrappers(), true)) {
    stream_wrapper_restore('php');
}
PHP;
        }

        $code = strtr($bootstrap, [
            '%DB_PATH%' => addslashes($dbPath),
            '%GET_VARS%' => var_export($get, true),
            '%SERVER_VARS%' => var_export($serverVars, true),
            '%SESSION_ID%' => var_export($sessionId, true),
            '%SESSION_VARS%' => var_export($session, true),
            '%RAW_BODY%' => var_export($rawBody ?? '', true),
            '%INPUT_WRAPPER_BOOTSTRAP%' => $inputWrapperBootstrap,
            '%INPUT_WRAPPER_RESTORE%' => $inputWrapperRestore,
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
