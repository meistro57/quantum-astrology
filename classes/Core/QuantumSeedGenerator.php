<?php
declare(strict_types=1);

namespace QuantumAstrology\Core;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;

/**
 * Generates high-entropy "quantum" seeds used to bootstrap astrological simulations.
 */
final class QuantumSeedGenerator
{
    private const MIN_ENTROPY_BYTES = 16;
    private const DEFAULT_ENTROPY_BYTES = 32;
    private const HARMONIC_SEGMENTS = 12;
    private const RESONANCE_SEGMENTS = 8;

    private readonly int $entropyBytes;
    private readonly DateTimeZone $timezone;

    public function __construct(?int $entropyBytes = null, ?DateTimeZone $timezone = null)
    {
        $entropyBytes ??= self::DEFAULT_ENTROPY_BYTES;

        if ($entropyBytes < self::MIN_ENTROPY_BYTES) {
            throw new InvalidArgumentException('Entropy must be at least ' . self::MIN_ENTROPY_BYTES . ' bytes.');
        }

        $this->entropyBytes = $entropyBytes;
        $this->timezone = $timezone ?? new DateTimeZone('UTC');
    }

    public function getEntropyBytes(): int
    {
        return $this->entropyBytes;
    }

    /**
     * Generate a single quantum seed instance.
     */
    public function generate(): QuantumSeed
    {
        $entropy = random_bytes($this->entropyBytes);
        $primarySeed = strtoupper(bin2hex($entropy));
        $hash = hash('sha256', $entropy, false);

        if ($hash === false) {
            throw new RuntimeException('Failed to derive hash for quantum seed.');
        }

        $harmonics = $this->deriveHarmonics($hash);
        $resonance = $this->deriveResonanceVector($hash);
        $checksum = strtoupper(substr(hash('sha1', $primarySeed . $hash), 0, 12));

        $generatedAt = new DateTimeImmutable('now', $this->timezone);

        return new QuantumSeed(
            $primarySeed,
            $checksum,
            $harmonics,
            $resonance,
            $this->entropyBytes,
            $generatedAt,
            'simulated-quantum-v1'
        );
    }

    /**
     * @return list<QuantumSeed>
     */
    public function generateBatch(int $count): array
    {
        if ($count < 1) {
            throw new InvalidArgumentException('Seed count must be at least 1.');
        }

        $seeds = [];
        $seenPrimaries = [];

        while (count($seeds) < $count) {
            $seed = $this->generate();

            if (isset($seenPrimaries[$seed->getPrimarySeed()])) {
                continue; // ensure uniqueness in the unlikely event of a collision
            }

            $seenPrimaries[$seed->getPrimarySeed()] = true;
            $seeds[] = $seed;
        }

        return $seeds;
    }

    /**
     * @return list<float>
     */
    private function deriveHarmonics(string $hash): array
    {
        $harmonics = [];
        $segmentLength = intdiv(strlen($hash), self::HARMONIC_SEGMENTS);
        $segmentLength = max(4, $segmentLength);

        for ($i = 0; $i < self::HARMONIC_SEGMENTS; $i++) {
            $offset = $i * $segmentLength;
            $chunk = substr($hash, $offset, $segmentLength);
            if ($chunk === '' || strlen($chunk) < 4) {
                $chunk = str_pad($chunk, 4, '0');
            }

            $value = hexdec(substr($chunk, 0, 4));
            $angle = round(($value / 0xFFFF) * 360, 2);
            $harmonics[] = $angle;
        }

        return $harmonics;
    }

    /**
     * @return list<float>
     */
    private function deriveResonanceVector(string $hash): array
    {
        $resonance = [];
        $segmentLength = intdiv(strlen($hash), self::RESONANCE_SEGMENTS);
        $segmentLength = max(8, $segmentLength);

        for ($i = 0; $i < self::RESONANCE_SEGMENTS; $i++) {
            $offset = $i * $segmentLength;
            $chunk = substr($hash, $offset, $segmentLength);
            if ($chunk === '' || strlen($chunk) < 8) {
                $chunk = str_pad($chunk, 8, '0');
            }

            $value = hexdec(substr($chunk, 0, 8));
            $resonance[] = round($value / 0xFFFFFFFF, 6);
        }

        return $resonance;
    }
}
