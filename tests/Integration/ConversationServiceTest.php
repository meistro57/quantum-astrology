<?php
declare(strict_types=1);

namespace QuantumAstrology\Tests\Integration;

use App\Services\ConversationService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ConversationServiceTest extends TestCase
{
    public function testReturnsFirstNonEmptyAssistantContent(): void
    {
        $service = new ConversationService(maxIterations: 5);

        $result = $service->runToolEnabledGeneration(
            static function (int $round): array {
                if ($round === 1) {
                    return ['content' => 'Ready response', 'tool_calls' => []];
                }
                return ['content' => '', 'tool_calls' => []];
            }
        );

        $this->assertSame('Ready response', $result['content'] ?? null);
        $this->assertSame(1, $result['round'] ?? null);
    }

    public function testReturnsWhenToolCallsAppearEvenWithoutContent(): void
    {
        $service = new ConversationService(maxIterations: 5);

        $result = $service->runToolEnabledGeneration(
            static function (int $round): array {
                if ($round < 3) {
                    return ['content' => '', 'tool_calls' => []];
                }

                return [
                    'content' => '',
                    'tool_calls' => [
                        ['name' => 'search', 'arguments' => ['q' => 'mars']],
                    ],
                ];
            }
        );

        $this->assertSame(3, $result['round'] ?? null);
        $this->assertNotEmpty($result['tool_calls'] ?? []);
    }

    public function testFallsBackInsteadOfThrowingWhenAllIterationsAreEmpty(): void
    {
        $service = new ConversationService(maxIterations: 5, strictMode: false, fallbackMessage: 'Fallback text');

        $result = $service->runToolEnabledGeneration(
            static fn (int $round): array => ['content' => '', 'tool_calls' => []]
        );

        $this->assertSame('Fallback text', $result['content'] ?? null);
        $this->assertSame('fallback', $result['meta']['status'] ?? null);
        $this->assertStringContainsString('remained empty after 5 iterations', (string)($result['meta']['error'] ?? ''));
        $this->assertSame(5, $result['meta']['round'] ?? null);
    }

    public function testStrictModeKeepsLegacyExceptionPath(): void
    {
        $service = new ConversationService(maxIterations: 5, strictMode: true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tool-enabled generation remained empty after 5 iterations.');

        $service->runToolEnabledGeneration(
            static fn (int $round): array => ['content' => '', 'tool_calls' => []]
        );
    }
}
