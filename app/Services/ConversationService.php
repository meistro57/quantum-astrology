<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Minimal conversation orchestrator for tool-enabled generation loops.
 *
 * Default behavior avoids hard-failing when a provider repeatedly returns
 * empty content with no tool calls. Set $strictMode=true to keep throw-on-empty.
 */
final class ConversationService
{
    private int $maxIterations;
    private bool $strictMode;
    private string $fallbackMessage;

    public function __construct(
        int $maxIterations = 5,
        bool $strictMode = false,
        string $fallbackMessage = 'I could not get a complete tool response right now. Please try again.'
    ) {
        $this->maxIterations = max(1, $maxIterations);
        $this->strictMode = $strictMode;
        $this->fallbackMessage = trim($fallbackMessage);
    }

    /**
     * @param callable(int):array<string,mixed> $invokeRound
     * @return array<string,mixed>
     */
    public function runToolEnabledGeneration(callable $invokeRound): array
    {
        $lastRound = 0;

        for ($round = 1; $round <= $this->maxIterations; $round++) {
            $lastRound = $round;
            $result = $invokeRound($round);

            $content = trim((string)($result['content'] ?? ''));
            $toolCalls = $result['tool_calls'] ?? [];

            if ($content !== '' || (is_array($toolCalls) && $toolCalls !== [])) {
                $result['round'] = $round;
                return $result;
            }
        }

        $message = sprintf(
            'Tool-enabled generation remained empty after %d iterations.',
            $this->maxIterations
        );

        if ($this->strictMode) {
            throw new RuntimeException($message);
        }

        return [
            'content' => $this->fallbackMessage !== '' ? $this->fallbackMessage : 'Please try again.',
            'tool_calls' => [],
            'meta' => [
                'status' => 'fallback',
                'error' => $message,
                'round' => $lastRound,
            ],
            'round' => $lastRound,
        ];
    }
}

