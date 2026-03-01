<?php
declare(strict_types=1);

namespace QuantumAstrology\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class ResolveMapLinkApiTest extends TestCase
{
    /** @runInSeparateProcess */
    public function testRejectsInvalidJson(): void
    {
        [$status, $output] = $this->runEndpoint('{bad-json');

        $this->assertSame(400, $status, $output);
        $payload = json_decode($output, true);
        $this->assertIsArray($payload, $output);
        $this->assertFalse($payload['ok'] ?? true);
        $this->assertSame('INVALID_JSON', $payload['error']['code'] ?? null);
    }

    /** @runInSeparateProcess */
    public function testRequiresUrl(): void
    {
        [$status, $output] = $this->runEndpoint(json_encode([]));

        $this->assertSame(400, $status, $output);
        $payload = json_decode($output, true);
        $this->assertIsArray($payload, $output);
        $this->assertFalse($payload['ok'] ?? true);
        $this->assertSame('URL_REQUIRED', $payload['error']['code'] ?? null);
    }

    /** @runInSeparateProcess */
    public function testReturnsUnableToResolveWhenNoCoordinatesFound(): void
    {
        [$status, $output] = $this->runEndpoint(
            json_encode(['url' => 'https://maps.example/link']),
            ['QA_MOCK_RESOLVE_MAP_LINK_FINAL_URL' => 'https://maps.example/no-coordinates']
        );

        $this->assertSame(422, $status, $output);
        $payload = json_decode($output, true);
        $this->assertIsArray($payload, $output);
        $this->assertFalse($payload['ok'] ?? true);
        $this->assertSame('UNABLE_TO_RESOLVE', $payload['error']['code'] ?? null);
    }

    /** @runInSeparateProcess */
    public function testReturnsInvalidCoordinatesForOutOfRangeResult(): void
    {
        [$status, $output] = $this->runEndpoint(
            json_encode(['url' => 'https://maps.example/link']),
            ['QA_MOCK_RESOLVE_MAP_LINK_FINAL_URL' => 'https://maps.google.com/@123.45,-74.0060,15z']
        );

        $this->assertSame(422, $status, $output);
        $payload = json_decode($output, true);
        $this->assertIsArray($payload, $output);
        $this->assertFalse($payload['ok'] ?? true);
        $this->assertSame('INVALID_COORDINATES', $payload['error']['code'] ?? null);
    }

    /** @runInSeparateProcess */
    public function testResolvesCoordinatesFromMockFinalUrl(): void
    {
        [$status, $output] = $this->runEndpoint(
            json_encode(['url' => 'https://maps.app.goo.gl/test']),
            ['QA_MOCK_RESOLVE_MAP_LINK_FINAL_URL' => 'https://maps.google.com/@40.7128,-74.0060,15z']
        );

        $this->assertSame(200, $status, $output);
        $payload = json_decode($output, true);
        $this->assertIsArray($payload, $output);
        $this->assertTrue($payload['ok'] ?? false);
        $this->assertSame(40.7128, $payload['latitude'] ?? null);
        $this->assertSame(-74.006, $payload['longitude'] ?? null);
    }

    private function runEndpoint(string $rawBody, array $mockEnv = []): array
    {
        $runnerPath = tempnam(sys_get_temp_dir(), 'qa_runner_');
        if ($runnerPath === false) {
            $this->fail('Failed to create temporary runner.');
        }

        $bootstrap = <<<'PHP'
<?php
foreach (%MOCK_ENV% as $k => $v) {
    putenv($k . '=' . $v);
    $_ENV[$k] = $v;
    $_SERVER[$k] = $v;
}
$_GET = [];
$_POST = [];
$_SERVER = array_merge($_SERVER, [
    'REQUEST_METHOD' => 'POST',
    'REQUEST_URI' => '/api/resolve_map_link.php',
    'HTTP_CONTENT_TYPE' => 'application/json',
]);
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
            '%MOCK_ENV%' => var_export($mockEnv, true),
            '%RAW_BODY%' => var_export($rawBody, true),
            '%ENTRYPOINT_PATH%' => var_export($this->projectPath('api/resolve_map_link.php'), true),
        ]);

        file_put_contents($runnerPath, $code);

        $output = [];
        $exitCode = 0;
        exec('php ' . escapeshellarg($runnerPath), $output, $exitCode);
        @unlink($runnerPath);

        $rawOutput = implode("\n", $output);
        $statusPos = strrpos($rawOutput, '__STATUS__');
        $this->assertNotFalse($statusPos, "Missing status marker:\n{$rawOutput}");

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
