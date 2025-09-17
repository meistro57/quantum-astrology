<?php
declare(strict_types=1);

namespace QuantumAstrology\Tests\Core;

use PHPUnit\Framework\TestCase;
use QuantumAstrology\Core\Env;
use ReflectionClass;

final class EnvTest extends TestCase
{
    /** @var list<string> */
    private array $keysToCleanup = [];

    protected function setUp(): void
    {
        $this->keysToCleanup = [];
        $this->resetStaticVars();
    }

    protected function tearDown(): void
    {
        foreach ($this->keysToCleanup as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }
    }

    public function testLoadParsesEnvironmentFile(): void
    {
        $this->registerCleanup('FOO', 'BAR', 'QUOTED', 'ESCAPED');

        $path = $this->createEnvFile(<<<'ENV'
FOO=bar
BAR = baz
# comment
QUOTED="value with spaces"
ESCAPED="Line\nBreak"
ENV);

        try {
            Env::load($path);
        } finally {
            @unlink($path);
        }

        $this->assertSame('bar', Env::get('FOO'));
        $this->assertSame('baz', Env::get('BAR'));
        $this->assertSame('value with spaces', Env::get('QUOTED'));
        $this->assertSame("Line\nBreak", Env::get('ESCAPED'));
    }

    public function testGetReturnsDefaultWhenVariableMissing(): void
    {
        $this->assertSame('fallback', Env::get('MISSING_KEY', 'fallback'));
        $this->assertNull(Env::get('MISSING_KEY'));
    }

    public function testExistingEnvironmentVariablesAreNotOverwritten(): void
    {
        $this->registerCleanup('EXISTS');

        putenv('EXISTS=preexisting');
        $_ENV['EXISTS'] = 'preexisting';
        $_SERVER['EXISTS'] = 'preexisting';

        $path = $this->createEnvFile("EXISTS=fromfile\n");

        try {
            Env::load($path);
        } finally {
            @unlink($path);
        }

        $this->assertSame('preexisting', Env::get('EXISTS'));
    }

    private function resetStaticVars(): void
    {
        $reflection = new ReflectionClass(Env::class);
        $property = $reflection->getProperty('vars');
        $property->setAccessible(true);
        $property->setValue(null, []);
    }

    private function registerCleanup(string ...$keys): void
    {
        foreach ($keys as $key) {
            if (!in_array($key, $this->keysToCleanup, true)) {
                $this->keysToCleanup[] = $key;
            }
        }
    }

    private function createEnvFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'qa_env_');
        if ($path === false) {
            $this->fail('Failed to create a temporary file for testing.');
        }

        file_put_contents($path, $contents);

        return $path;
    }
}
