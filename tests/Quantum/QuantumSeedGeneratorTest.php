<?php
declare(strict_types=1);

namespace QuantumAstrology\Tests\Quantum;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use QuantumAstrology\Core\QuantumSeed;
use QuantumAstrology\Core\QuantumSeedGenerator;

final class QuantumSeedGeneratorTest extends TestCase
{
    public function testGenerateProducesSeedWithExpectedShape(): void
    {
        $generator = new QuantumSeedGenerator(32);
        $seed = $generator->generate();

        $this->assertSame(32, $seed->getEntropyBytes());
        $this->assertMatchesRegularExpression('/^[A-F0-9]{64}$/', $seed->getPrimarySeed());
        $this->assertMatchesRegularExpression('/^[A-F0-9]{12}$/', $seed->getChecksum());
        $this->assertCount(12, $seed->getHarmonics());
        $this->assertCount(8, $seed->getResonanceVector());
        $this->assertNotEmpty($seed->getSource());
        $this->assertSame('simulated-quantum-v1', $seed->getSource());
    }

    public function testGenerateBatchProducesRequestedNumberOfSeeds(): void
    {
        $generator = new QuantumSeedGenerator(24);
        $seeds = $generator->generateBatch(5);

        $this->assertCount(5, $seeds);

        $primaries = array_map(
            static fn (QuantumSeed $seed): string => $seed->getPrimarySeed(),
            $seeds
        );
        $this->assertCount(5, array_unique($primaries));
    }

    public function testGeneratorRejectsInvalidEntropy(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new QuantumSeedGenerator(8);
    }

    public function testGenerateBatchRejectsInvalidCount(): void
    {
        $generator = new QuantumSeedGenerator();

        $this->expectException(InvalidArgumentException::class);
        $generator->generateBatch(0);
    }
}
