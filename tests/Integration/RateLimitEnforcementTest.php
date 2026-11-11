<?php
declare(strict_types=1);

namespace QuantumAstrology\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Exercises rate limiting through the real HTTP entrypoint so status codes
 * and headers are observed exactly as clients see them.
 */
final class RateLimitEnforcementTest extends TestCase
{
    /** @var resource|null */
    private $server = null;

    private ?string $dbPath = null;

    private int $port = 0;

    protected function tearDown(): void
    {
        if (is_resource($this->server)) {
            proc_terminate($this->server);
            proc_close($this->server);
        }
        $this->server = null;

        if ($this->dbPath !== null) {
            @unlink($this->dbPath);
            $this->dbPath = null;
        }
    }

    public function testOverLimitRequestReturns429WithRateLimitHeaders(): void
    {
        $this->startServer([
            'RATE_LIMIT_FREE_PER_HOUR' => '2',
            'RATE_LIMIT_AI_PER_HOUR' => '1',
            'RATE_LIMIT_WINDOW_SECONDS' => '60',
        ]);

        [$firstStatus, $firstHeaders] = $this->get('/api/charts/1/wheel');
        [$secondStatus, $secondHeaders] = $this->get('/api/charts/2/wheel');
        [$thirdStatus, $thirdHeaders, $thirdBody] = $this->get('/api/charts/3/wheel');

        $this->assertSame(404, $firstStatus);
        $this->assertSame(404, $secondStatus);
        $this->assertSame(429, $thirdStatus, $thirdBody);

        $this->assertSame('2', $firstHeaders['x-ratelimit-limit'] ?? null);
        $this->assertSame('1', $firstHeaders['x-ratelimit-remaining'] ?? null);
        $this->assertSame('60', $firstHeaders['x-ratelimit-window'] ?? null);
        $this->assertSame('0', $secondHeaders['x-ratelimit-remaining'] ?? null);
        $this->assertArrayHasKey('retry-after', $thirdHeaders);
        $this->assertSame('0', $thirdHeaders['x-ratelimit-remaining'] ?? null);

        $payload = json_decode($thirdBody, true);
        $this->assertIsArray($payload, $thirdBody);
        $this->assertFalse($payload['success'] ?? true);
        $this->assertSame('RATE_LIMITED', $payload['error_code'] ?? null);
        $this->assertSame(2, $payload['error_details']['limit'] ?? null);
        $this->assertArrayHasKey('retry_after', $payload['meta'] ?? []);
    }

    public function testLimitResetsWhenWindowExpires(): void
    {
        $this->startServer([
            'RATE_LIMIT_FREE_PER_HOUR' => '1',
            'RATE_LIMIT_WINDOW_SECONDS' => '2',
        ]);

        [$firstStatus] = $this->get('/api/charts/1/wheel');
        [$secondStatus] = $this->get('/api/charts/1/wheel');

        $this->assertSame(404, $firstStatus);
        $this->assertSame(429, $secondStatus);

        sleep(3);

        [$thirdStatus] = $this->get('/api/charts/1/wheel');
        $this->assertSame(404, $thirdStatus);
    }

    public function testAiEndpointsUseStricterLimit(): void
    {
        $this->startServer([
            'RATE_LIMIT_FREE_PER_HOUR' => '50',
            'RATE_LIMIT_AI_PER_HOUR' => '1',
            'RATE_LIMIT_WINDOW_SECONDS' => '60',
        ]);

        [$firstStatus, $firstHeaders] = $this->get('/api/reports/horoscope.php');
        [$secondStatus, , $secondBody] = $this->get('/api/reports/horoscope.php');

        $this->assertSame(401, $firstStatus, 'Unauthenticated request should reach the endpoint.');
        $this->assertSame('1', $firstHeaders['x-ratelimit-limit'] ?? null);
        $this->assertSame(429, $secondStatus, $secondBody);

        $payload = json_decode($secondBody, true);
        $this->assertIsArray($payload, $secondBody);
        $this->assertSame('RATE_LIMITED', $payload['error_code'] ?? null);
    }

    /**
     * @param array<string,string> $overrides
     */
    private function startServer(array $overrides): void
    {
        $dbPath = tempnam(sys_get_temp_dir(), 'qa_rate_limit_');
        if ($dbPath === false) {
            $this->fail('Failed to allocate a temporary SQLite file.');
        }
        $this->dbPath = $dbPath;

        $projectRoot = dirname(__DIR__, 2);
        $environment = array_merge(
            [
                'PATH' => (string) (getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin'),
                'HOME' => (string) (getenv('HOME') ?: sys_get_temp_dir()),
                'TMPDIR' => sys_get_temp_dir(),
            ],
            $overrides,
            [
                'DB_DRIVER' => 'sqlite',
                'DB_SQLITE_PATH' => $dbPath,
            ]
        );

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->port = random_int(20000, 45000);
            $command = sprintf(
                '%s -S 127.0.0.1:%d %s',
                escapeshellarg(PHP_BINARY),
                $this->port,
                escapeshellarg($projectRoot . '/index.php')
            );

            $server = proc_open(
                $command,
                [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
                $pipes,
                $projectRoot,
                $environment
            );

            if (!is_resource($server)) {
                continue;
            }

            if ($this->waitForServer()) {
                $this->server = $server;
                return;
            }

            proc_terminate($server);
            proc_close($server);
        }

        $this->fail('Unable to start the PHP built-in server for the rate limit test.');
    }

    private function waitForServer(): bool
    {
        $deadline = microtime(true) + 5.0;

        while (microtime(true) < $deadline) {
            $socket = @fsockopen('127.0.0.1', $this->port, $errno, $errstr, 0.2);
            if (is_resource($socket)) {
                fclose($socket);
                return true;
            }
            usleep(50_000);
        }

        return false;
    }

    /**
     * @return array{0:int,1:array<string,string>,2:string}
     */
    private function get(string $path): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'ignore_errors' => true,
                'timeout' => 15,
            ],
        ]);

        $body = @file_get_contents('http://127.0.0.1:' . $this->port . $path, false, $context);
        $rawHeaders = $http_response_header ?? [];

        if ($body === false) {
            $this->fail('Request to ' . $path . ' failed.');
        }

        $status = 0;
        $headers = [];
        foreach ($rawHeaders as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $matches) === 1) {
                $status = (int) $matches[1];
                continue;
            }

            $separator = strpos($line, ':');
            if ($separator === false) {
                continue;
            }

            $headers[strtolower(trim(substr($line, 0, $separator)))] = trim(substr($line, $separator + 1));
        }

        return [$status, $headers, $body];
    }
}
