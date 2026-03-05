<?php
declare(strict_types=1);

namespace QuantumAstrology\Interpretations;

use QuantumAstrology\Charts\Chart;
use QuantumAstrology\Interpretations\ChartInterpretation;
use QuantumAstrology\Core\Logger;
use QuantumAstrology\Core\SystemSettings;
use QuantumAstrology\Support\AIProviderResponse;

class AIInterpreter
{
    private Chart $chart;
    private array $aiConfig;
    private string $apiEndpoint;
    private string $apiKey;
    private string $provider;
    private bool $allowMockResponses = false;

    // Supported AI providers
    private const PROVIDERS = [
        'ollama' => 'http://localhost:11434',
        'openrouter' => 'https://openrouter.ai/api/v1',
        'openai' => 'https://api.openai.com/v1',
        'anthropic' => 'https://api.anthropic.com/v1',
        'deepseek' => 'https://api.deepseek.com/v1',
        'gemini' => 'https://generativelanguage.googleapis.com/v1beta',
    ];

    // Default models per provider
    private const DEFAULT_MODELS = [
        'ollama' => 'llama3.1',
        'openrouter' => 'anthropic/claude-3.5-sonnet',
        'openai' => 'gpt-4o-mini',
        'anthropic' => 'claude-sonnet-4-20250514',
        'deepseek' => 'deepseek-chat',
        'gemini' => 'gemini-1.5-flash',
    ];

    public function __construct(Chart $chart, array $aiConfig = [])
    {
        $this->chart = $chart;
        $this->aiConfig = $aiConfig;
        $this->initializeAIConfig();
    }

    /**
     * Initialize AI configuration from environment or defaults
     */
    private function initializeAIConfig(): void
    {
        $master = SystemSettings::getMasterAiConfig();

        // Determine provider from config or environment
        $this->provider = strtolower($this->aiConfig['provider'] ?? $master['provider'] ?? $_ENV['AI_PROVIDER'] ?? 'ollama');
        if (!isset(self::PROVIDERS[$this->provider])) {
            $this->provider = 'ollama';
        }

        // Get API key from config or environment
        $this->apiKey = $this->aiConfig['api_key'] ?? $master['api_key'] ?? $_ENV['AI_API_KEY'] ?? '';

        // Set endpoint based on provider or use custom endpoint
        if (isset($this->aiConfig['endpoint'])) {
            $this->apiEndpoint = $this->aiConfig['endpoint'];
        } elseif (isset($_ENV['AI_API_ENDPOINT'])) {
            $this->apiEndpoint = $_ENV['AI_API_ENDPOINT'];
        } elseif (isset(self::PROVIDERS[$this->provider])) {
            $this->apiEndpoint = self::PROVIDERS[$this->provider];
        } else {
            $this->apiEndpoint = 'mock';
        }

        $allowMock = $this->aiConfig['allow_mock']
            ?? $_ENV['AI_ALLOW_MOCK']
            ?? (defined('APP_ENV') && APP_ENV !== 'production');
        $this->allowMockResponses = filter_var($allowMock, FILTER_VALIDATE_BOOLEAN);

        // Never silently downgrade to mock in production for cloud providers.
        if (empty($this->apiKey) && $this->provider !== 'ollama') {
            Logger::warning('AI provider configured without API key', [
                'provider' => $this->provider,
                'app_env' => defined('APP_ENV') ? APP_ENV : null,
            ]);
        }
    }

    /**
     * Get the current provider name
     */
    public function getProvider(): string
    {
        return $this->provider;
    }

    /**
     * Get the model being used
     */
    public function getModel(): string
    {
        $master = SystemSettings::getMasterAiConfig();

        $configuredModel = trim((string) ($this->aiConfig['model'] ?? ''));
        if ($configuredModel !== '' && strtolower($configuredModel) !== 'default') {
            return $configuredModel;
        }

        $masterModel = trim((string) ($master['model'] ?? ''));
        if ($masterModel !== '' && strtolower($masterModel) !== 'default') {
            return $masterModel;
        }

        $envModel = trim((string) ($_ENV['AI_MODEL'] ?? ''));
        if ($envModel !== '' && strtolower($envModel) !== 'default') {
            return $envModel;
        }

        return self::DEFAULT_MODELS[$this->provider] ?? 'default';
    }

    /**
     * Generate AI-powered natural language chart interpretation
     */
    public function generateNaturalLanguageInterpretation(): array
    {
        try {
            // Get structured interpretation first
            $interpreter = new ChartInterpretation($this->chart);
            $structuredData = $interpreter->generateFullInterpretation();

            // Generate natural language sections
            $sections = [
                'personality_overview' => $this->generatePersonalityOverview($structuredData),
                'life_purpose' => $this->generateLifePurpose($structuredData),
                'relationships' => $this->generateRelationshipInsights($structuredData),
                'career_guidance' => $this->generateCareerGuidance($structuredData),
                'challenges_growth' => $this->generateChallengesAndGrowth($structuredData),
                'timing_advice' => $this->generateTimingAdvice($structuredData)
            ];

            // Generate overall synthesis
            $synthesis = $this->generateOverallSynthesis($structuredData, $sections);

            return [
                'chart_id' => $this->chart->getId(),
                'chart_name' => $this->chart->getName(),
                'interpretation_type' => 'ai_natural_language',
                'personality_overview' => $sections['personality_overview'],
                'life_purpose' => $sections['life_purpose'],
                'relationship_insights' => $sections['relationships'],
                'career_guidance' => $sections['career_guidance'],
                'challenges_and_growth' => $sections['challenges_growth'],
                'timing_advice' => $sections['timing_advice'],
                'overall_synthesis' => $synthesis,
                'confidence_score' => $this->calculateConfidenceScore($structuredData),
                'interpretation_metadata' => [
                    'generated_at' => date('c'),
                    'ai_model' => $this->getModelInfo(),
                    'processing_time' => 0, // To be calculated
                    'word_count' => $this->calculateWordCount($sections)
                ]
            ];

        } catch (\Exception $e) {
            Logger::error("AI interpretation failed", [
                'error' => $e->getMessage(),
                'chart_id' => $this->chart->getId()
            ]);

            // Return fallback interpretation
            return $this->generateFallbackInterpretation();
        }
    }

    /**
     * Generate a concise AI summary in a single model call to avoid long request chains.
     */
    public function generateSummaryReport(string $reportType = 'natal'): array
    {
        try {
            $interpreter = new ChartInterpretation($this->chart);
            $structuredData = $interpreter->generateFullInterpretation();

            $personalityPrompt = $this->buildSinglePassSummaryPrompt($structuredData, $reportType);
            $personalityText = $this->callAI($personalityPrompt, 'summary_personality');
            if (!is_string($personalityText) || trim($personalityText) === '') {
                return $this->generateFallbackInterpretation();
            }

            $cleanPersonality = $this->cleanAIResponse($personalityText);

            $synthesisPrompt = "Create an 'Overall Synthesis' section for a {$reportType} astrology report.\n"
                . "Write 2-4 paragraphs that integrate the most important chart themes into practical, cohesive guidance.\n"
                . "Do not repeat the exact wording from other sections. Avoid bullet lists and markdown headers.\n\n"
                . "Personality overview context:\n"
                . $cleanPersonality;
            $synthesisText = $this->callAI($synthesisPrompt, 'summary_synthesis');
            $cleanSynthesis = (is_string($synthesisText) && trim($synthesisText) !== '')
                ? $this->cleanAIResponse($synthesisText)
                : $cleanPersonality;

            $sections = [
                'personality_overview' => $cleanPersonality,
                'life_purpose' => '',
                'relationships' => '',
                'career_guidance' => '',
                'challenges_growth' => '',
                'timing_advice' => '',
            ];

            return [
                'chart_id' => $this->chart->getId(),
                'chart_name' => $this->chart->getName(),
                'interpretation_type' => 'ai_summary_single_pass',
                'personality_overview' => $cleanPersonality,
                'life_purpose' => '',
                'relationship_insights' => '',
                'career_guidance' => '',
                'challenges_and_growth' => '',
                'timing_advice' => '',
                'overall_synthesis' => $cleanSynthesis,
                'confidence_score' => $this->calculateConfidenceScore($structuredData),
                'interpretation_metadata' => [
                    'generated_at' => date('c'),
                    'ai_model' => $this->getModelInfo(),
                    'processing_time' => 0,
                    'word_count' => $this->calculateWordCount($sections),
                ]
            ];
        } catch (\Throwable $e) {
            Logger::error("AI summary generation failed", [
                'error' => $e->getMessage(),
                'chart_id' => $this->chart->getId(),
            ]);
            return $this->generateFallbackInterpretation();
        }
    }

    /**
     * Generate a full-featured AI horoscope for a specific period.
     *
     * @param array<int,string> $areas
     * @return array<string,mixed>
     */
    public function generateHoroscope(
        string $period = 'daily',
        ?string $forDate = null,
        array $areas = [],
        string $focus = ''
    ): array {
        $period = strtolower(trim($period));
        if (!in_array($period, ['daily', 'weekly', 'monthly', 'yearly'], true)) {
            $period = 'daily';
        }

        $dateLabel = $forDate ?: date('Y-m-d');
        $areas = array_values(array_unique(array_filter(array_map(
            static fn ($v): string => strtolower(trim((string)$v)),
            $areas
        ), static fn ($v): bool => $v !== '')));
        $allowedAreas = ['love', 'career', 'health', 'finance', 'spiritual', 'social', 'creativity', 'family', 'general'];
        $areas = array_values(array_filter($areas, static fn ($v): bool => in_array($v, $allowedAreas, true)));
        if ($areas === []) {
            $areas = ['general', 'love', 'career', 'health', 'finance'];
        }

        try {
            $interpreter = new ChartInterpretation($this->chart);
            $structuredData = $interpreter->generateFullInterpretation();

            $core = $structuredData['core_identity'] ?? [];
            $sun = (string)($core['sun']['sign'] ?? 'Unknown');
            $moon = (string)($core['moon']['sign'] ?? 'Unknown');
            $rising = (string)($core['rising']['sign'] ?? 'Unknown');
            $dominantElement = (string)($structuredData['elemental_balance']['dominant_element'] ?? 'balanced');
            $dominantMode = (string)($structuredData['modal_balance']['dominant_mode'] ?? 'balanced');
            $lifePurpose = (string)($structuredData['overall_themes']['life_purpose'] ?? '');

            $scopeMap = [
                'daily' => 'next 24 hours',
                'weekly' => 'next 7 days',
                'monthly' => 'current month window',
                'yearly' => 'current year window',
            ];
            $scope = $scopeMap[$period] ?? 'upcoming period';
            $focusText = trim($focus);

            $prompt = "Create a {$period} horoscope for {$dateLabel} covering {$scope}.\n"
                . "Chart facts: Sun {$sun}, Moon {$moon}, Rising {$rising}, dominant element {$dominantElement}, dominant mode {$dominantMode}.\n"
                . ($lifePurpose !== '' ? "Life purpose anchor: {$lifePurpose}\n" : '')
                . "Requested focus areas: " . implode(', ', $areas) . ".\n"
                . ($focusText !== '' ? "User focus: {$focusText}\n" : '')
                . "Return Markdown only using this structure:\n"
                . "# {$period} Horoscope\n"
                . "## Overview\n"
                . "## Opportunities\n"
                . "## Challenges\n"
                . "## Love\n"
                . "## Career\n"
                . "## Health & Energy\n"
                . "## Finance\n"
                . "## Spiritual Guidance\n"
                . "## Action Steps\n"
                . "## Lucky Indicators\n"
                . "Under each section, provide concise practical guidance, no fluff, no disclaimers.";

            $text = $this->callAI($prompt, 'horoscope_' . $period);
            if (!is_string($text) || trim($text) === '') {
                return $this->generateFallbackHoroscope($period, $dateLabel, $areas);
            }

            $markdown = $this->cleanAIResponse($text);
            if (!str_contains($markdown, '#') || !str_contains(strtolower($markdown), 'overview')) {
                return $this->generateFallbackHoroscope($period, $dateLabel, $areas);
            }

            return [
                'chart_id' => $this->chart->getId(),
                'chart_name' => $this->chart->getName(),
                'period' => $period,
                'for_date' => $dateLabel,
                'areas' => $areas,
                'horoscope_markdown' => $markdown,
                'confidence_score' => $this->calculateConfidenceScore($structuredData),
                'interpretation_metadata' => [
                    'generated_at' => date('c'),
                    'ai_model' => $this->getModelInfo(),
                    'processing_time' => 0,
                    'word_count' => str_word_count($markdown),
                ],
            ];
        } catch (\Throwable $e) {
            Logger::error('AI horoscope generation failed', [
                'error' => $e->getMessage(),
                'chart_id' => $this->chart->getId(),
                'period' => $period,
            ]);
            return $this->generateFallbackHoroscope($period, $dateLabel, $areas);
        }
    }

    /**
     * Generate personality overview
     */
    private function generatePersonalityOverview(array $structuredData): string
    {
        $prompt = $this->buildPersonalityPrompt($structuredData);
        $response = $this->callAI($prompt, 'personality_analysis');

        if ($response) {
            return $this->cleanAIResponse($response);
        }

        // Fallback to template-based generation
        return $this->generateTemplatePersonality($structuredData);
    }

    /**
     * Generate life purpose analysis
     */
    private function generateLifePurpose(array $structuredData): string
    {
        $prompt = $this->buildLifePurposePrompt($structuredData);
        $response = $this->callAI($prompt, 'life_purpose');

        if ($response) {
            return $this->cleanAIResponse($response);
        }

        return $this->generateTemplateLifePurpose($structuredData);
    }

    /**
     * Generate relationship insights
     */
    private function generateRelationshipInsights(array $structuredData): string
    {
        $prompt = $this->buildRelationshipPrompt($structuredData);
        $response = $this->callAI($prompt, 'relationships');

        if ($response) {
            return $this->cleanAIResponse($response);
        }

        return $this->generateTemplateRelationships($structuredData);
    }

    /**
     * Generate career guidance
     */
    private function generateCareerGuidance(array $structuredData): string
    {
        $prompt = $this->buildCareerPrompt($structuredData);
        $response = $this->callAI($prompt, 'career');

        if ($response) {
            return $this->cleanAIResponse($response);
        }

        return $this->generateTemplateCareer($structuredData);
    }

    /**
     * Generate challenges and growth opportunities
     */
    private function generateChallengesAndGrowth(array $structuredData): string
    {
        $prompt = $this->buildChallengesPrompt($structuredData);
        $response = $this->callAI($prompt, 'challenges');

        if ($response) {
            return $this->cleanAIResponse($response);
        }

        return $this->generateTemplateChallenges($structuredData);
    }

    /**
     * Generate timing advice
     */
    private function generateTimingAdvice(array $structuredData): string
    {
        $prompt = $this->buildTimingPrompt($structuredData);
        $response = $this->callAI($prompt, 'timing');

        if ($response) {
            return $this->cleanAIResponse($response);
        }

        return $this->generateTemplateTiming($structuredData);
    }

    /**
     * Generate overall synthesis
     */
    private function generateOverallSynthesis(array $structuredData, array $sections): string
    {
        $prompt = $this->buildSynthesisPrompt($structuredData, $sections);
        $response = $this->callAI($prompt, 'synthesis');

        if ($response) {
            return $this->cleanAIResponse($response);
        }

        return $this->generateTemplateSynthesis($structuredData);
    }

    /**
     * Build AI prompt for personality analysis
     */
    private function buildPersonalityPrompt(array $data): string
    {
        $coreIdentity = $data['core_identity'] ?? [];
        $dominantPlanets = $data['dominant_planets']['dominant_planets'] ?? [];
        $elementalBalance = $data['elemental_balance'] ?? [];

        $prompt = "As a professional astrologer, provide a 3-4 paragraph personality analysis for this birth chart:\n\n";

        if (isset($coreIdentity['sun'])) {
            $prompt .= "Sun in {$coreIdentity['sun']['sign']} in House {$coreIdentity['sun']['house']}\n";
        }
        if (isset($coreIdentity['moon'])) {
            $prompt .= "Moon in {$coreIdentity['moon']['sign']} in House {$coreIdentity['moon']['house']}\n";
        }
        if (isset($coreIdentity['rising'])) {
            $prompt .= "{$coreIdentity['rising']['sign']} Rising\n";
        }

        $prompt .= "Dominant planets: " . implode(', ', array_map('ucfirst', $dominantPlanets)) . "\n";
        $prompt .= "Elemental emphasis: {$elementalBalance['dominant_element']} dominant\n\n";

        $prompt .= "Focus on core personality traits, natural inclinations, and how this person typically expresses themselves. Write in a warm, insightful tone that speaks directly to the individual. Avoid generic statements and focus on the unique combination shown in this chart.";

        return $prompt;
    }

    /**
     * Build AI prompt for life purpose analysis
     */
    private function buildLifePurposePrompt(array $data): string
    {
        $themes = $data['overall_themes'] ?? [];
        $houseEmphasis = $data['house_emphasis'] ?? [];

        $prompt = "Based on this birth chart analysis, provide 2-3 paragraphs about this person's life purpose and soul mission:\n\n";

        if (!empty($themes['life_purpose'])) {
            $prompt .= "Core life purpose theme: {$themes['life_purpose']}\n";
        }

        if (!empty($houseEmphasis['stelliums'])) {
            foreach ($houseEmphasis['stelliums'] as $stellium) {
                $prompt .= "Stellium in House {$stellium['house']}: " . implode(', ', $stellium['planets']) . "\n";
            }
        }

        $prompt .= "\nDiscuss their soul's evolutionary path, karmic lessons, and how they can best serve their highest purpose. Be inspiring yet practical.";

        return $prompt;
    }

    /**
     * Build one compact prompt for fast summary generation.
     */
    private function buildSinglePassSummaryPrompt(array $data, string $reportType): string
    {
        $core = $data['core_identity'] ?? [];
        $sun = isset($core['sun']['sign']) ? (string) $core['sun']['sign'] : 'Unknown';
        $moon = isset($core['moon']['sign']) ? (string) $core['moon']['sign'] : 'Unknown';
        $rising = isset($core['rising']['sign']) ? (string) $core['rising']['sign'] : 'Unknown';
        $dominantElement = (string)($data['elemental_balance']['dominant_element'] ?? 'balanced');
        $dominantMode = (string)($data['modal_balance']['dominant_mode'] ?? 'balanced');
        $lifePurpose = (string)($data['overall_themes']['life_purpose'] ?? '');
        $style = strtolower(trim((string)($this->aiConfig['style'] ?? 'professional')));
        $length = strtolower(trim((string)($this->aiConfig['length'] ?? 'short')));

        $lengthInstruction = match ($length) {
            'long' => 'Write 8-12 paragraphs with deeper nuance and concrete examples.',
            'medium' => 'Write 6-9 paragraphs with balanced detail.',
            default => 'Write 4-6 concise paragraphs with high signal.',
        };
        $styleInstruction = match ($style) {
            'empathetic' => 'Tone: warm, empathetic, supportive, and non-judgmental.',
            'direct' => 'Tone: direct, clear, practical, and to the point.',
            'technical' => 'Tone: technical and precise while still readable.',
            default => 'Tone: professional, insightful, and grounded.',
        };

        return "Create one astrology summary for a {$reportType} report.\n"
            . $lengthInstruction . "\n"
            . "Chart facts: Sun {$sun}, Moon {$moon}, Rising {$rising}, dominant element {$dominantElement}, dominant mode {$dominantMode}.\n"
            . ($lifePurpose !== '' ? "Life purpose signal: {$lifePurpose}\n" : '')
            . $styleInstruction . "\n"
            . "Requirements: non-generic, no bullet lists, no markdown headers.";
    }

    /**
     * Call AI service or return mock response
     */
    private function callAI(string $prompt, string $category): ?string
    {
        $focus = trim((string) ($this->aiConfig['focus'] ?? ''));
        if ($focus !== '') {
            $prompt .= "\n\nAdditional user focus: " . $focus;
        }

        if ($this->apiEndpoint === 'mock' || $this->provider === 'mock') {
            if (!$this->allowMockResponses) {
                Logger::warning('Mock AI response requested but mock mode is disabled', [
                    'provider' => $this->provider,
                    'category' => $category,
                ]);
                return null;
            }
            return $this->getMockResponse($category);
        }

        try {
            // Implementation for real AI API calls (Ollama, OpenAI, etc.)
            $response = $this->makeAPIRequest($prompt);
            return $response;
        } catch (\Exception $e) {
            Logger::warning("AI API call failed", [
                'category' => $category,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Make actual API request to AI service
     */
    private function makeAPIRequest(string $prompt): ?string
    {
        $startTime = microtime(true);

        // Route to the correct provider
        $response = match ($this->provider) {
            'openrouter' => $this->callOpenRouter($prompt),
            'openai' => $this->callOpenAI($prompt),
            'anthropic' => $this->callClaude($prompt),
            'deepseek' => $this->callDeepSeek($prompt),
            'gemini' => $this->callGemini($prompt),
            'ollama' => $this->callOllama($prompt),
            default => $this->callOllama($prompt),
        };

        $processingTime = round((microtime(true) - $startTime) * 1000);
        Logger::debug("AI API request completed", [
            'provider' => $this->provider,
            'model' => $this->getModel(),
            'time_ms' => $processingTime
        ]);

        return $response;
    }

    /**
     * Call Ollama local LLM API
     */
    private function callOllama(string $prompt): ?string
    {
        $model = $this->getModel();
        $endpoint = rtrim($this->apiEndpoint, '/') . '/api/generate';

        $payload = [
            'model' => $model,
            'prompt' => $prompt,
            'system' => $this->getSystemPrompt(),
            'stream' => false,
            'options' => [
                'temperature' => 0.7,
                'top_p' => 0.9,
                'num_predict' => 1024
            ]
        ];

        $result = $this->postJsonWithRetry(
            $endpoint,
            $payload,
            ['Content-Type: application/json'],
            120
        );
        if (!$result['ok']) {
            $this->logProviderFailure('Ollama', $result, $model);
            return null;
        }

        return AIProviderResponse::extractText('ollama', $result['json']);
    }

    /**
     * Call OpenAI API (GPT models)
     */
    private function callOpenAI(string $prompt): ?string
    {
        if (empty($this->apiKey)) {
            Logger::warning("OpenAI API key not configured");
            return null;
        }

        $model = $this->getModel();
        $endpoint = 'https://api.openai.com/v1/chat/completions';

        $payload = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->getSystemPrompt()
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.7,
            'max_tokens' => 1024
        ];

        $result = $this->postJsonWithRetry(
            $endpoint,
            $payload,
            [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            60
        );
        if (!$result['ok']) {
            $this->logProviderFailure('OpenAI', $result, $model);
            return null;
        }

        return AIProviderResponse::extractText('openai', $result['json']);
    }

    /**
     * Call Anthropic Claude API
     */
    private function callClaude(string $prompt): ?string
    {
        if (empty($this->apiKey)) {
            Logger::warning("Claude API key not configured");
            return null;
        }

        $model = $this->getModel();
        $endpoint = 'https://api.anthropic.com/v1/messages';

        $payload = [
            'model' => $model,
            'max_tokens' => 1024,
            'system' => $this->getSystemPrompt(),
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ]
        ];

        $result = $this->postJsonWithRetry(
            $endpoint,
            $payload,
            [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
            ],
            60
        );
        if (!$result['ok']) {
            $this->logProviderFailure('Anthropic', $result, $model);
            return null;
        }

        return AIProviderResponse::extractText('anthropic', $result['json']);
    }

    /**
     * Call OpenRouter API (unified gateway to multiple models)
     * Supports Claude, GPT-4, Llama, Mistral, and many more
     */
    private function callOpenRouter(string $prompt): ?string
    {
        if (empty($this->apiKey)) {
            Logger::warning("OpenRouter API key not configured");
            return null;
        }

        $model = $this->getModel();
        $endpoint = rtrim($this->apiEndpoint, '/') . '/chat/completions';

        $payload = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->getSystemPrompt()
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.7,
            'max_tokens' => 1024
        ];

        $result = $this->postJsonWithRetry(
            $endpoint,
            $payload,
            [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
                'HTTP-Referer: ' . ($_ENV['APP_URL'] ?? 'https://quantum-astrology.local'),
                'X-Title: ' . (defined('APP_NAME') ? APP_NAME : 'Quantum Astrology'),
            ],
            90
        );
        if (!$result['ok']) {
            $this->logProviderFailure('OpenRouter', $result, $model);
            return null;
        }

        return AIProviderResponse::extractText('openrouter', $result['json']);
    }

    /**
     * Call DeepSeek API
     */
    private function callDeepSeek(string $prompt): ?string
    {
        if (empty($this->apiKey)) {
            Logger::warning("DeepSeek API key not configured");
            return null;
        }

        $model = $this->getModel();
        $endpoint = rtrim($this->apiEndpoint, '/') . '/chat/completions';

        $payload = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->getSystemPrompt()
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.7,
            'max_tokens' => 1024
        ];

        $result = $this->postJsonWithRetry(
            $endpoint,
            $payload,
            [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            90
        );
        if (!$result['ok']) {
            $this->logProviderFailure('DeepSeek', $result, $model);
            return null;
        }

        return AIProviderResponse::extractText('deepseek', $result['json']);
    }

    /**
     * Call Google Gemini API
     */
    private function callGemini(string $prompt): ?string
    {
        if (empty($this->apiKey)) {
            Logger::warning("Gemini API key not configured");
            return null;
        }

        $model = $this->getModel();
        $endpoint = rtrim($this->apiEndpoint, '/') . '/models/' . $model . ':generateContent';

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        [
                            'text' => $this->getSystemPrompt() . "\n\n" . $prompt
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 1024
            ]
        ];

        $result = $this->postJsonWithRetry(
            $endpoint,
            $payload,
            [
                'Content-Type: application/json',
                'x-goog-api-key: ' . $this->apiKey,
            ],
            90
        );
        if (!$result['ok']) {
            $this->logProviderFailure('Gemini', $result, $model);
            return null;
        }

        return AIProviderResponse::extractText('gemini', $result['json']);
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<int,string> $headers
     * @return array{ok:bool,http_status:int,error:string,json:array<string,mixed>|null,retries:int}
     */
    private function postJsonWithRetry(
        string $url,
        array $payload,
        array $headers,
        int $timeoutSeconds = 90
    ): array {
        if (!function_exists('curl_init')) {
            return [
                'ok' => false,
                'http_status' => 0,
                'error' => 'cURL extension unavailable',
                'json' => null,
                'retries' => 0,
            ];
        }

        $maxRetries = max(0, (int)($_ENV['AI_HTTP_RETRIES'] ?? 2));
        $connectTimeout = max(3, (int)($_ENV['AI_HTTP_CONNECT_TIMEOUT'] ?? 10));
        $sleepMsBase = max(100, (int)($_ENV['AI_HTTP_RETRY_DELAY_MS'] ?? 350));
        $attempt = 0;

        while (true) {
            $attempt++;
            $ch = curl_init($url);
            if ($ch === false) {
                return [
                    'ok' => false,
                    'http_status' => 0,
                    'error' => 'Failed to initialize cURL',
                    'json' => null,
                    'retries' => $attempt - 1,
                ];
            }

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => max(10, $timeoutSeconds),
                CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            ]);

            $raw = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            $decoded = is_string($raw) ? json_decode($raw, true) : null;
            $retryableHttp = in_array($httpCode, [408, 409, 425, 429, 500, 502, 503, 504], true);
            $shouldRetry = ($curlError !== '' || $retryableHttp) && $attempt <= ($maxRetries + 1);

            if ($curlError === '' && $httpCode >= 200 && $httpCode < 300 && is_array($decoded)) {
                return [
                    'ok' => true,
                    'http_status' => $httpCode,
                    'error' => '',
                    'json' => $decoded,
                    'retries' => $attempt - 1,
                ];
            }

            if (!$shouldRetry || $attempt > ($maxRetries + 1)) {
                $errorMessage = $curlError !== ''
                    ? $curlError
                    : AIProviderResponse::extractError(is_array($decoded) ? $decoded : null, $httpCode);

                return [
                    'ok' => false,
                    'http_status' => $httpCode,
                    'error' => $errorMessage,
                    'json' => is_array($decoded) ? $decoded : null,
                    'retries' => $attempt - 1,
                ];
            }

            usleep(($sleepMsBase * $attempt) * 1000);
        }
    }

    /**
     * @param array{ok:bool,http_status:int,error:string,json:array<string,mixed>|null,retries:int} $result
     */
    private function logProviderFailure(string $providerName, array $result, string $model): void
    {
        Logger::warning($providerName . ' API call failed', [
            'provider' => strtolower($providerName),
            'model' => $model,
            'status' => $result['http_status'],
            'retries' => $result['retries'],
            'error' => $result['error'],
        ]);
    }

    /**
     * Get mock response for demonstration
     */
    private function getMockResponse(string $category): string
    {
        if (str_starts_with($category, 'horoscope_')) {
            $period = strtoupper(str_replace('horoscope_', '', $category));
            return "# {$period} Horoscope\n\n"
                . "## Overview\n\nMomentum is steady. Prioritize one major theme and avoid overcommitting.\n\n"
                . "## Opportunities\n\nConnections and practical decisions support progress if you act early.\n\n"
                . "## Challenges\n\nWatch impulsive responses and vague planning; details matter.\n\n"
                . "## Love\n\nUse direct communication and avoid assumptions.\n\n"
                . "## Career\n\nBest window for structured execution and follow-through.\n\n"
                . "## Health & Energy\n\nProtect sleep consistency and reduce cognitive overload.\n\n"
                . "## Finance\n\nFavor conservative moves and clear budgeting over speculation.\n\n"
                . "## Spiritual Guidance\n\nPause daily for reflection before key decisions.\n\n"
                . "## Action Steps\n\n- Pick top 3 priorities\n- Time-block deep work\n- Close one lingering obligation\n\n"
                . "## Lucky Indicators\n\n- Numbers: 3, 8\n- Colors: gold, deep blue\n- Best time: early evening";
        }

        $mockResponses = [
            'personality_analysis' => "Your personality is a fascinating blend of determination and creativity, shaped by the powerful combination of your core planetary placements. You approach life with natural confidence and possess an innate ability to inspire others through your authentic self-expression. Your emotional nature is both deep and intuitive, allowing you to connect with others on a profound level while maintaining your independent spirit.\n\nThere's a natural magnetism to your personality that draws people toward you, yet you value your personal freedom and space. You have excellent instincts about people and situations, often knowing things intuitively before they become obvious. Your approach to life tends to be both practical and idealistic, seeking to manifest your visions in tangible ways.\n\nYou express yourself with natural authority and creativity, often becoming a catalyst for positive change in your environment. While you can be quite focused and determined when pursuing your goals, you also possess the flexibility to adapt when circumstances require it. Others likely see you as someone who is both reliable and inspiring, capable of both leading and supporting as the situation demands.",

            'life_purpose' => "Your life purpose centers around bridging the gap between vision and reality, helping to manifest positive change in the world through your unique combination of creativity, leadership, and deep emotional intelligence. You are here to learn how to balance your personal desires with service to others, discovering that true fulfillment comes through contributing to something larger than yourself.\n\nYour soul's evolutionary path involves mastering the art of authentic leadership - not through force or manipulation, but through inspiring others by example and creating space for everyone to shine. There are important karmic lessons around learning to trust your intuition while also developing practical skills to implement your ideas effectively.\n\nThe highest expression of your purpose involves using your natural gifts to help heal and transform your immediate environment, whether through creative expression, nurturing relationships, or innovative problem-solving. You have the potential to be a bridge between different worlds or perspectives, helping others see new possibilities and find their own path to fulfillment.",

            'relationships' => "In relationships, you bring a powerful combination of passion and loyalty, seeking deep, meaningful connections that allow for both intimacy and personal growth. You have a natural ability to understand others' emotional needs and tend to attract partners who appreciate your depth and authenticity. However, you may sometimes struggle with balancing your need for independence with your desire for close partnership.\n\nYour approach to love is intense and transformative - you don't do shallow connections well. You need partners who can match your emotional depth and who aren't threatened by your strong personality. You tend to be very protective of those you love and can be quite intuitive about their needs, sometimes knowing what they need before they do.\n\nThe key to successful relationships for you lies in learning to communicate your needs clearly while also giving your partners space to be themselves. You have the capacity for profound, healing relationships that transform both you and your partner, but this requires ongoing work on maintaining healthy boundaries and avoiding the tendency to either over-give or become too controlling.",

            'career' => "Your career path is likely to involve some form of creative leadership, healing, or transformational work where you can use your natural ability to inspire and guide others. You have excellent instincts for understanding what people need and the vision to see how things could be improved or transformed. Fields that might appeal to you include counseling, creative arts, entrepreneurship, healing professions, or any work that allows you to be both independent and impactful.\n\nYou work best in environments that allow for creativity, autonomy, and meaningful impact. Traditional corporate structures might feel restrictive unless you can find a way to innovate within them. You have natural business instincts and the ability to see opportunities that others might miss, making you well-suited for entrepreneurial ventures.\n\nSuccess comes most naturally when you can align your work with your values and when you feel you're making a real difference. You need work that engages both your mind and your heart, and you tend to excel when given the freedom to develop your own methods and approaches. The key is finding or creating opportunities that honor your need for both security and creative expression."
        ];

        return $mockResponses[$category] ?? $this->getGenericMockResponse();
    }

    private function getSystemPrompt(): string
    {
        $configured = trim((string)($this->aiConfig['system_prompt'] ?? ''));
        if ($configured !== '') {
            return $configured;
        }

        $summaryCfg = SystemSettings::getAiSummaryConfig();
        $fromSettings = trim((string)($summaryCfg['system_prompt'] ?? ''));
        if ($fromSettings !== '') {
            return $fromSettings;
        }

        return 'You are a professional astrologer providing insightful, personalized chart interpretations. Write in a warm, empathetic tone that speaks directly to the individual. Avoid generic statements and focus on the unique combination shown in each chart.';
    }

    /**
     * Clean and format AI response
     */
    private function cleanAIResponse(string $response): string
    {
        // Remove any unwanted formatting, ensure proper paragraph breaks
        $cleaned = trim($response);
        $cleaned = preg_replace('/\n{3,}/', "\n\n", $cleaned); // Normalize paragraph breaks
        $cleaned = preg_replace('/^\s*[-*]\s*/m', '', $cleaned); // Remove bullet points

        return $cleaned;
    }

    /**
     * Calculate confidence score based on data completeness
     */
    private function calculateConfidenceScore(array $data): int
    {
        $score = 0;

        // Check core components
        if (isset($data['core_identity']['sun'])) $score += 20;
        if (isset($data['core_identity']['moon'])) $score += 20;
        if (isset($data['core_identity']['rising'])) $score += 15;

        // Check aspect patterns
        if (!empty($data['aspect_patterns']['patterns'])) $score += 15;

        // Check house emphasis
        if (!empty($data['house_emphasis']['stelliums'])) $score += 10;

        // Check elemental/modal balance
        if (isset($data['elemental_balance']['dominant_element'])) $score += 10;
        if (isset($data['modal_balance']['dominant_mode'])) $score += 10;

        return min(100, $score);
    }

    /**
     * Calculate total word count
     */
    private function calculateWordCount(array $sections): int
    {
        $totalWords = 0;
        foreach ($sections as $section) {
            $totalWords += str_word_count($section);
        }
        return $totalWords;
    }

    /**
     * Get AI model information
     */
    private function getModelInfo(): string
    {
        if ($this->apiEndpoint === 'mock') {
            return 'Mock AI Responses v1.0';
        }

        $model = $this->getModel();
        $providerNames = [
            'ollama' => 'Ollama',
            'openrouter' => 'OpenRouter',
            'openai' => 'OpenAI',
            'anthropic' => 'Anthropic',
            'deepseek' => 'DeepSeek',
            'gemini' => 'Google Gemini',
        ];

        $providerName = $providerNames[$this->provider] ?? ucfirst($this->provider);
        return $providerName . ' ' . $model;
    }

    /**
     * Get list of supported providers with their configuration info
     */
    public static function getSupportedProviders(): array
    {
        return [
            'ollama' => [
                'name' => 'Ollama (Local)',
                'description' => 'Run AI models locally with Ollama',
                'requires_key' => false,
                'default_model' => 'llama3.1',
                'models' => ['llama3.1', 'llama3.2', 'mistral', 'mixtral', 'codellama', 'phi3'],
            ],
            'openrouter' => [
                'name' => 'OpenRouter',
                'description' => 'Unified gateway to 100+ AI models',
                'requires_key' => true,
                'default_model' => 'anthropic/claude-3.5-sonnet',
                'models' => [
                    'anthropic/claude-3.5-sonnet',
                    'anthropic/claude-3-opus',
                    'openai/gpt-4o',
                    'openai/gpt-4o-mini',
                    'meta-llama/llama-3.1-70b-instruct',
                    'google/gemini-pro-1.5',
                    'mistralai/mistral-large',
                ],
            ],
            'openai' => [
                'name' => 'OpenAI',
                'description' => 'GPT-4 and ChatGPT models',
                'requires_key' => true,
                'default_model' => 'gpt-4o-mini',
                'models' => ['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-3.5-turbo'],
            ],
            'anthropic' => [
                'name' => 'Anthropic',
                'description' => 'Claude AI models',
                'requires_key' => true,
                'default_model' => 'claude-sonnet-4-20250514',
                'models' => ['claude-sonnet-4-20250514', 'claude-3-5-sonnet-20241022', 'claude-3-opus-20240229'],
            ],
            'deepseek' => [
                'name' => 'DeepSeek',
                'description' => 'Cost-effective AI with strong reasoning',
                'requires_key' => true,
                'default_model' => 'deepseek-chat',
                'models' => ['deepseek-chat', 'deepseek-coder'],
            ],
            'gemini' => [
                'name' => 'Google Gemini',
                'description' => 'Google\'s multimodal AI models',
                'requires_key' => true,
                'default_model' => 'gemini-1.5-flash',
                'models' => ['gemini-1.5-flash', 'gemini-1.5-pro', 'gemini-pro'],
            ],
        ];
    }

    /**
     * Generate fallback interpretation when AI fails
     */
    private function generateFallbackInterpretation(): array
    {
        return [
            'chart_id' => $this->chart->getId(),
            'chart_name' => $this->chart->getName(),
            'interpretation_type' => 'fallback_template',
            'personality_overview' => 'Chart interpretation is available through structured analysis.',
            'life_purpose' => 'Life purpose insights available through detailed chart review.',
            'relationship_insights' => 'Relationship patterns can be explored through synastry analysis.',
            'career_guidance' => 'Career direction indicated by planetary placements and aspects.',
            'challenges_and_growth' => 'Growth opportunities present in challenging aspect patterns.',
            'timing_advice' => 'Timing guidance available through transit and progression analysis.',
            'overall_synthesis' => 'This chart contains rich information for personal understanding and growth.',
            'confidence_score' => 50,
            'interpretation_metadata' => [
                'generated_at' => date('c'),
                'ai_model' => 'Fallback Template System',
                'processing_time' => 0,
                'word_count' => 100
            ]
        ];
    }

    /**
     * @param array<int,string> $areas
     * @return array<string,mixed>
     */
    private function generateFallbackHoroscope(string $period, string $dateLabel, array $areas): array
    {
        $title = ucfirst($period) . ' Horoscope';
        $markdown = "# {$title}\n\n"
            . "## Overview\n\nA steady period for intentional action and disciplined choices.\n\n"
            . "## Opportunities\n\nFocus on high-leverage tasks and transparent communication.\n\n"
            . "## Challenges\n\nAvoid scattered attention and emotionally reactive decisions.\n\n"
            . "## Love\n\nConsistency and clear expectations strengthen connection.\n\n"
            . "## Career\n\nProgress comes through sequencing priorities and finishing what is started.\n\n"
            . "## Health & Energy\n\nSimplify routines to preserve energy and reduce stress.\n\n"
            . "## Finance\n\nUse conservative planning and track discretionary spending.\n\n"
            . "## Spiritual Guidance\n\nReflect before major commitments; align action with values.\n\n"
            . "## Action Steps\n\n- Define top priorities\n- Protect focus blocks\n- Review commitments at day end\n\n"
            . "## Lucky Indicators\n\n- Numbers: 2, 7\n- Colors: navy, gold\n- Best time: morning";

        return [
            'chart_id' => $this->chart->getId(),
            'chart_name' => $this->chart->getName(),
            'period' => $period,
            'for_date' => $dateLabel,
            'areas' => $areas,
            'horoscope_markdown' => $markdown,
            'confidence_score' => 50,
            'interpretation_metadata' => [
                'generated_at' => date('c'),
                'ai_model' => 'Fallback Template System',
                'processing_time' => 0,
                'word_count' => str_word_count($markdown),
            ],
        ];
    }

    // Template-based fallback methods
    private function generateTemplatePersonality(array $data): string
    {
        $coreIdentity = $data['core_identity'] ?? [];

        $personality = "Your personality is shaped by a unique combination of planetary influences. ";

        if (isset($coreIdentity['sun'])) {
            $personality .= "With your Sun in {$coreIdentity['sun']['sign']}, you express yourself with natural {$coreIdentity['sun']['sign']} qualities. ";
        }

        if (isset($coreIdentity['moon'])) {
            $personality .= "Your Moon in {$coreIdentity['moon']['sign']} reveals your emotional nature and instinctive responses. ";
        }

        if (isset($coreIdentity['rising'])) {
            $personality .= "Your {$coreIdentity['rising']['sign']} Rising gives you a distinctive approach to life and how others first perceive you.";
        }

        return $personality;
    }

    private function generateTemplateLifePurpose(array $data): string
    {
        return "Your life purpose is revealed through the unique combination of planetary placements in your chart. The emphasis on certain houses and signs suggests areas where you're meant to focus your energy and develop your potential.";
    }

    private function generateTemplateRelationships(array $data): string
    {
        return "Your approach to relationships is influenced by your Venus placement, Moon sign, and 7th house configuration. These factors reveal your romantic nature, emotional needs, and partnership patterns.";
    }

    private function generateTemplateCareer(array $data): string
    {
        return "Your career potential is indicated by your Midheaven, 10th house planets, and the overall pattern of your chart. These placements suggest fields where you can best express your talents and achieve success.";
    }

    private function generateTemplateChallenges(array $data): string
    {
        return "Your growth challenges are revealed through difficult aspects and planetary tensions in your chart. These areas of challenge also represent your greatest potential for development and mastery.";
    }

    private function generateTemplateTiming(array $data): string
    {
        return "The timing of important life events can be understood through progressions, transits, and solar returns to your natal chart. These techniques reveal optimal periods for different types of activities and growth.";
    }

    private function generateTemplateSynthesis(array $data): string
    {
        return "Your birth chart reveals a complex and fascinating individual with unique gifts, challenges, and potential. The integration of all these factors creates the blueprint for your personal journey of growth and self-actualization.";
    }

    private function getGenericMockResponse(): string
    {
        return "This aspect of your chart reveals important information about your personality and life path. The planetary placements and aspects create a unique pattern that influences how you approach life and relationships.";
    }

    // Additional prompt builders for other categories
    private function buildRelationshipPrompt(array $data): string
    {
        return "Analyze relationship patterns based on Venus, Mars, Moon placements and 7th house configuration...";
    }

    private function buildCareerPrompt(array $data): string
    {
        return "Provide career guidance based on Midheaven, 10th house planets, and overall chart emphasis...";
    }

    private function buildChallengesPrompt(array $data): string
    {
        return "Discuss growth challenges and opportunities based on difficult aspects and planetary tensions...";
    }

    private function buildTimingPrompt(array $data): string
    {
        return "Provide general timing principles based on the natal chart structure...";
    }

    private function buildSynthesisPrompt(array $data, array $sections): string
    {
        return "Create an overall synthesis that ties together all aspects of this person's chart...";
    }
}
