<?php
declare(strict_types=1);

namespace QuantumAstrology\Support;

final class AIProviderResponse
{
    /**
     * @param array<string,mixed>|null $json
     */
    public static function extractText(string $provider, ?array $json): ?string
    {
        if (!is_array($json)) {
            return null;
        }

        $provider = strtolower(trim($provider));
        return match ($provider) {
            'anthropic' => self::anthropicText($json),
            'gemini' => self::geminiText($json),
            'ollama' => self::scalarText($json['response'] ?? null),
            default => self::chatCompletionText($json),
        };
    }

    /**
     * @param array<string,mixed>|null $json
     */
    public static function extractError(?array $json, int $httpStatus): string
    {
        if (!is_array($json)) {
            return $httpStatus > 0 ? ('HTTP ' . $httpStatus) : 'Request failed';
        }

        if (isset($json['error']) && is_string($json['error']) && trim($json['error']) !== '') {
            return trim($json['error']);
        }
        if (isset($json['error']['message']) && is_string($json['error']['message'])) {
            return trim($json['error']['message']);
        }
        if (isset($json['message']) && is_string($json['message']) && trim($json['message']) !== '') {
            return trim($json['message']);
        }
        if (isset($json['detail']) && is_string($json['detail']) && trim($json['detail']) !== '') {
            return trim($json['detail']);
        }

        return $httpStatus > 0 ? ('HTTP ' . $httpStatus) : 'Request failed';
    }

    /**
     * @param mixed $value
     */
    private static function scalarText(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $text = trim($value);
        return $text === '' ? null : $text;
    }

    /**
     * @param array<string,mixed> $json
     */
    private static function chatCompletionText(array $json): ?string
    {
        $content = $json['choices'][0]['message']['content'] ?? null;
        if (is_string($content)) {
            $content = trim($content);
            return $content === '' ? null : $content;
        }

        if (is_array($content)) {
            $parts = [];
            foreach ($content as $part) {
                if (is_array($part) && isset($part['text']) && is_string($part['text'])) {
                    $parts[] = trim($part['text']);
                }
            }
            $joined = trim(implode("\n", array_filter($parts, static fn ($p) => $p !== '')));
            return $joined === '' ? null : $joined;
        }

        return null;
    }

    /**
     * @param array<string,mixed> $json
     */
    private static function anthropicText(array $json): ?string
    {
        $content = $json['content'] ?? null;
        if (!is_array($content)) {
            return null;
        }

        $parts = [];
        foreach ($content as $part) {
            if (is_array($part) && (($part['type'] ?? '') === 'text') && isset($part['text']) && is_string($part['text'])) {
                $parts[] = trim($part['text']);
            }
        }
        $joined = trim(implode("\n", array_filter($parts, static fn ($p) => $p !== '')));
        return $joined === '' ? null : $joined;
    }

    /**
     * @param array<string,mixed> $json
     */
    private static function geminiText(array $json): ?string
    {
        $parts = $json['candidates'][0]['content']['parts'] ?? null;
        if (!is_array($parts)) {
            return null;
        }

        $textParts = [];
        foreach ($parts as $part) {
            if (is_array($part) && isset($part['text']) && is_string($part['text'])) {
                $textParts[] = trim($part['text']);
            }
        }

        $joined = trim(implode("\n", array_filter($textParts, static fn ($p) => $p !== '')));
        return $joined === '' ? null : $joined;
    }
}
