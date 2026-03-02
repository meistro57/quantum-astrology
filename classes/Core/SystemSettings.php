<?php
declare(strict_types=1);

namespace QuantumAstrology\Core;

use PDO;
use Throwable;

final class SystemSettings
{
    private const TABLE = 'system_settings';

    private static bool $tableEnsured = false;

    /**
     * @return array{provider:string,model:string,api_key:string,api_key_set:bool,updated_at:?string}
     */
    public static function getMasterAiConfig(): array
    {
        self::ensureTable();

        $provider = (string) self::get('ai_master_provider', (string) ($_ENV['AI_PROVIDER'] ?? 'ollama'));
        $model = (string) self::get('ai_master_model', (string) ($_ENV['AI_MODEL'] ?? ''));
        $apiKey = (string) self::get('ai_master_api_key', (string) ($_ENV['AI_API_KEY'] ?? ''));
        $updatedAt = self::getUpdatedAt('ai_master_api_key');

        return [
            'provider' => strtolower(trim($provider)) !== '' ? strtolower(trim($provider)) : 'ollama',
            'model' => trim($model),
            'api_key' => $apiKey,
            'api_key_set' => trim($apiKey) !== '',
            'updated_at' => $updatedAt,
        ];
    }

    public static function setMasterAiConfig(string $provider, string $model, ?string $apiKey, bool $clearKey = false): void
    {
        self::ensureTable();

        self::set('ai_master_provider', strtolower(trim($provider)));
        self::set('ai_master_model', trim($model));

        if ($clearKey) {
            self::set('ai_master_api_key', '');
            return;
        }

        if ($apiKey !== null && trim($apiKey) !== '') {
            self::set('ai_master_api_key', trim($apiKey));
        }
    }

    /**
     * @return array{
     *   system_prompt:string,
     *   style:string,
     *   length:string,
     *   focus_template:string,
     *   updated_at:?string
     * }
     */
    public static function getAiSummaryConfig(): array
    {
        self::ensureTable();

        $systemPrompt = (string) self::get(
            'ai_summary_system_prompt',
            'You are a professional astrologer providing insightful, personalized chart interpretations. Write in a warm, empathetic tone that speaks directly to the individual. Avoid generic statements and focus on the unique combination shown in each chart.'
        );
        $style = strtolower(trim((string) self::get('ai_summary_style', 'professional')));
        $length = strtolower(trim((string) self::get('ai_summary_length', 'short')));
        $focusTemplate = (string) self::get(
            'ai_summary_focus_template',
            'Prioritize a concise summary suitable for a {report_type} report.'
        );

        if (!in_array($style, ['professional', 'empathetic', 'direct', 'technical'], true)) {
            $style = 'professional';
        }
        if (!in_array($length, ['short', 'medium', 'long'], true)) {
            $length = 'short';
        }

        return [
            'system_prompt' => trim($systemPrompt),
            'style' => $style,
            'length' => $length,
            'focus_template' => trim($focusTemplate),
            'updated_at' => self::getUpdatedAt('ai_summary_system_prompt'),
        ];
    }

    public static function setAiSummaryConfig(string $systemPrompt, string $style, string $length, string $focusTemplate): void
    {
        self::ensureTable();
        self::set('ai_summary_system_prompt', trim($systemPrompt));
        self::set('ai_summary_style', strtolower(trim($style)));
        self::set('ai_summary_length', strtolower(trim($length)));
        self::set('ai_summary_focus_template', trim($focusTemplate));
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        self::ensureTable();
        $pdo = DB::conn();
        $stmt = $pdo->prepare('SELECT setting_value FROM ' . self::TABLE . ' WHERE setting_key = :k LIMIT 1');
        $stmt->execute([':k' => $key]);
        $value = $stmt->fetchColumn();
        if ($value === false || $value === null) {
            return $default;
        }
        return (string) $value;
    }

    public static function set(string $key, string $value): void
    {
        self::ensureTable();
        $pdo = DB::conn();

        $update = $pdo->prepare('UPDATE ' . self::TABLE . ' SET setting_value = :v, updated_at = CURRENT_TIMESTAMP WHERE setting_key = :k');
        $update->execute([':k' => $key, ':v' => $value]);
        if ($update->rowCount() > 0) {
            return;
        }

        try {
            $insert = $pdo->prepare('INSERT INTO ' . self::TABLE . ' (setting_key, setting_value, updated_at) VALUES (:k, :v, CURRENT_TIMESTAMP)');
            $insert->execute([':k' => $key, ':v' => $value]);
        } catch (Throwable) {
            // Handle race between concurrent inserts by retrying update.
            $update->execute([':k' => $key, ':v' => $value]);
        }
    }

    private static function getUpdatedAt(string $key): ?string
    {
        self::ensureTable();
        $pdo = DB::conn();
        $stmt = $pdo->prepare('SELECT updated_at FROM ' . self::TABLE . ' WHERE setting_key = :k LIMIT 1');
        $stmt->execute([':k' => $key]);
        $value = $stmt->fetchColumn();
        return $value === false || $value === null ? null : (string) $value;
    }

    private static function ensureTable(): void
    {
        if (self::$tableEnsured) {
            return;
        }

        $pdo = DB::conn();
        $sql = 'CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' (
            setting_key VARCHAR(191) PRIMARY KEY,
            setting_value TEXT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        )';
        $pdo->exec($sql);
        self::$tableEnsured = true;
    }
}
