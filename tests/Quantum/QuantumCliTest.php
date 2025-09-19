<?php
declare(strict_types=1);

namespace QuantumAstrology\Tests\Quantum;

use PHPUnit\Framework\TestCase;

final class QuantumCliTest extends TestCase
{
    private string $script;

    protected function setUp(): void
    {
        $this->script = __DIR__ . '/../../tools/quantum-cli.php';
    }

    public function testJsonOutputIsValid(): void
    {
        $command = sprintf(
            '%s %s seed --count=2 --entropy=24 --json',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($this->script)
        );

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        $this->assertSame(0, $exitCode, 'CLI exited with error: ' . implode(PHP_EOL, $output));

        $json = implode(PHP_EOL, $output);
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(2, $data['count']);
        $this->assertSame(24, $data['entropy_bytes']);
        $this->assertArrayHasKey('seeds', $data);
        $this->assertCount(2, $data['seeds']);

        $first = $data['seeds'][0];
        $this->assertArrayHasKey('primary', $first);
        $this->assertArrayHasKey('checksum', $first);
        $this->assertArrayHasKey('harmonics', $first);
        $this->assertArrayHasKey('resonance_vector', $first);
    }

    public function testHumanReadableOutputContainsKeySections(): void
    {
        $command = sprintf(
            '%s %s seed --count=1 --entropy=20',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($this->script)
        );

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        $this->assertSame(0, $exitCode, 'CLI exited with error: ' . implode(PHP_EOL, $output));

        $text = implode(PHP_EOL, $output);
        $this->assertStringContainsString('Quantum Seed #1', $text);
        $this->assertStringContainsString('Primary Seed:', $text);
        $this->assertStringContainsString('Harmonics:', $text);
        $this->assertStringContainsString('Resonance Vector:', $text);
    }

    public function testInvalidCountProducesError(): void
    {
        $command = sprintf(
            '%s %s seed --count=0',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($this->script)
        );

        $output = [];
        $exitCode = 0;
        exec($command . ' 2>&1', $output, $exitCode);

        $this->assertSame(1, $exitCode);
        $combined = implode(PHP_EOL, $output);
        $this->assertStringContainsString('Error:', $combined);
    }
}
