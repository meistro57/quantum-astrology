<?php
declare(strict_types=1);

namespace QuantumAstrology\Core;

use DateTimeImmutable;
use JsonSerializable;

/**
 * Value object representing a generated quantum seed and its derived metrics.
 */
final class QuantumSeed implements JsonSerializable
{
    /** @var list<float> */
    private array $harmonics;

    /** @var list<float> */
    private array $resonanceVector;

    public function __construct(
        private readonly string $primary,
        private readonly string $checksum,
        array $harmonics,
        array $resonanceVector,
        private readonly int $entropyBytes,
        private readonly DateTimeImmutable $generatedAt,
        private readonly string $source
    ) {
        $this->harmonics = array_values(array_map(
            static fn (float|int $value): float => (float) $value,
            $harmonics
        ));
        $this->resonanceVector = array_values(array_map(
            static fn (float|int $value): float => (float) $value,
            $resonanceVector
        ));
    }

    public function getPrimarySeed(): string
    {
        return $this->primary;
    }

    public function getChecksum(): string
    {
        return $this->checksum;
    }

    /**
     * @return list<float>
     */
    public function getHarmonics(): array
    {
        return $this->harmonics;
    }

    /**
     * @return list<float>
     */
    public function getResonanceVector(): array
    {
        return $this->resonanceVector;
    }

    public function getEntropyBytes(): int
    {
        return $this->entropyBytes;
    }

    public function getGeneratedAt(): DateTimeImmutable
    {
        return $this->generatedAt;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * Return a human-readable representation of the seed suitable for CLI output.
     */
    public function format(): string
    {
        $lines = [
            'Primary Seed: ' . $this->primary,
            'Checksum: ' . $this->checksum,
            'Generated: ' . $this->generatedAt->format(DATE_ATOM),
            'Entropy: ' . $this->entropyBytes . ' bytes',
            'Harmonics: ' . $this->formatAngles($this->harmonics),
            'Resonance Vector: ' . $this->formatResonance($this->resonanceVector),
            'Source: ' . $this->source,
        ];

        return implode(PHP_EOL, $lines);
    }

    /**
     * @return array{
     *     primary: string,
     *     checksum: string,
     *     generated_at: string,
     *     entropy_bytes: int,
     *     harmonics: list<float>,
     *     resonance_vector: list<float>,
     *     source: string
     * }
     */
    public function toArray(): array
    {
        return [
            'primary' => $this->primary,
            'checksum' => $this->checksum,
            'generated_at' => $this->generatedAt->format(DATE_ATOM),
            'entropy_bytes' => $this->entropyBytes,
            'harmonics' => $this->harmonics,
            'resonance_vector' => $this->resonanceVector,
            'source' => $this->source,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @param list<float> $angles
     */
    private function formatAngles(array $angles): string
    {
        $formatted = array_map(
            static fn (float $angle): string => number_format($angle, 2) . "\u{00B0}",
            $angles
        );

        return implode(', ', $formatted);
    }

    /**
     * @param list<float> $resonance
     */
    private function formatResonance(array $resonance): string
    {
        $formatted = array_map(
            static fn (float $value): string => number_format($value, 6),
            $resonance
        );

        return implode(', ', $formatted);
    }
}
