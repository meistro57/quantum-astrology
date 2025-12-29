<?php
declare(strict_types=1);

namespace QuantumAstrology\Interpretations;

use QuantumAstrology\Charts\Chart;
use QuantumAstrology\Interpretations\ChartInterpretation;
use QuantumAstrology\Core\Logger;

class AIInterpreter
{
    private Chart $chart;
    private array $aiConfig;
    private string $apiEndpoint;
    private string $apiKey;
    
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
        $this->apiEndpoint = $this->aiConfig['endpoint'] ?? $_ENV['AI_API_ENDPOINT'] ?? 'http://localhost:11434';
        $this->apiKey = $this->aiConfig['api_key'] ?? $_ENV['AI_API_KEY'] ?? '';
        
        // Default to local Ollama installation or mock responses for demo
        if (empty($this->apiEndpoint)) {
            $this->apiEndpoint = 'mock'; // Use mock responses for demonstration
        }
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
     * Call AI service or return mock response
     */
    private function callAI(string $prompt, string $category): ?string
    {
        if ($this->apiEndpoint === 'mock') {
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

        // Determine which AI service to use
        if (str_contains($this->apiEndpoint, 'openai.com')) {
            $response = $this->callOpenAI($prompt);
        } elseif (str_contains($this->apiEndpoint, 'anthropic.com')) {
            $response = $this->callClaude($prompt);
        } else {
            // Default to Ollama (local LLM)
            $response = $this->callOllama($prompt);
        }

        $processingTime = round((microtime(true) - $startTime) * 1000);
        Logger::debug("AI API request completed", ['time_ms' => $processingTime]);

        return $response;
    }

    /**
     * Call Ollama local LLM API
     */
    private function callOllama(string $prompt): ?string
    {
        $model = $this->aiConfig['model'] ?? $_ENV['OLLAMA_MODEL'] ?? 'llama3.1';
        $endpoint = rtrim($this->apiEndpoint, '/') . '/api/generate';

        $payload = [
            'model' => $model,
            'prompt' => $prompt,
            'stream' => false,
            'options' => [
                'temperature' => 0.7,
                'top_p' => 0.9,
                'num_predict' => 1024
            ]
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 120, // 2 minute timeout for generation
            CURLOPT_CONNECTTIMEOUT => 10
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            Logger::warning("Ollama API connection failed", ['error' => $error]);
            return null;
        }

        if ($httpCode !== 200) {
            Logger::warning("Ollama API returned non-200 status", ['status' => $httpCode, 'response' => $response]);
            return null;
        }

        $data = json_decode($response, true);
        return $data['response'] ?? null;
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

        $model = $this->aiConfig['model'] ?? 'gpt-4o-mini';
        $endpoint = 'https://api.openai.com/v1/chat/completions';

        $payload = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a professional astrologer providing insightful, personalized chart interpretations. Write in a warm, empathetic tone that speaks directly to the individual. Avoid generic statements and focus on the unique combination shown in each chart.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.7,
            'max_tokens' => 1024
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey
            ],
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || $httpCode !== 200) {
            Logger::warning("OpenAI API call failed", ['error' => $error, 'status' => $httpCode]);
            return null;
        }

        $data = json_decode($response, true);
        return $data['choices'][0]['message']['content'] ?? null;
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

        $model = $this->aiConfig['model'] ?? 'claude-sonnet-4-20250514';
        $endpoint = 'https://api.anthropic.com/v1/messages';

        $payload = [
            'model' => $model,
            'max_tokens' => 1024,
            'system' => 'You are a professional astrologer providing insightful, personalized chart interpretations. Write in a warm, empathetic tone that speaks directly to the individual. Avoid generic statements and focus on the unique combination shown in each chart.',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ]
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01'
            ],
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || $httpCode !== 200) {
            Logger::warning("Claude API call failed", ['error' => $error, 'status' => $httpCode]);
            return null;
        }

        $data = json_decode($response, true);
        return $data['content'][0]['text'] ?? null;
    }
    
    /**
     * Get mock response for demonstration
     */
    private function getMockResponse(string $category): string
    {
        $mockResponses = [
            'personality_analysis' => "Your personality is a fascinating blend of determination and creativity, shaped by the powerful combination of your core planetary placements. You approach life with natural confidence and possess an innate ability to inspire others through your authentic self-expression. Your emotional nature is both deep and intuitive, allowing you to connect with others on a profound level while maintaining your independent spirit.\n\nThere's a natural magnetism to your personality that draws people toward you, yet you value your personal freedom and space. You have excellent instincts about people and situations, often knowing things intuitively before they become obvious. Your approach to life tends to be both practical and idealistic, seeking to manifest your visions in tangible ways.\n\nYou express yourself with natural authority and creativity, often becoming a catalyst for positive change in your environment. While you can be quite focused and determined when pursuing your goals, you also possess the flexibility to adapt when circumstances require it. Others likely see you as someone who is both reliable and inspiring, capable of both leading and supporting as the situation demands.",
            
            'life_purpose' => "Your life purpose centers around bridging the gap between vision and reality, helping to manifest positive change in the world through your unique combination of creativity, leadership, and deep emotional intelligence. You are here to learn how to balance your personal desires with service to others, discovering that true fulfillment comes through contributing to something larger than yourself.\n\nYour soul's evolutionary path involves mastering the art of authentic leadership - not through force or manipulation, but through inspiring others by example and creating space for everyone to shine. There are important karmic lessons around learning to trust your intuition while also developing practical skills to implement your ideas effectively.\n\nThe highest expression of your purpose involves using your natural gifts to help heal and transform your immediate environment, whether through creative expression, nurturing relationships, or innovative problem-solving. You have the potential to be a bridge between different worlds or perspectives, helping others see new possibilities and find their own path to fulfillment.",
            
            'relationships' => "In relationships, you bring a powerful combination of passion and loyalty, seeking deep, meaningful connections that allow for both intimacy and personal growth. You have a natural ability to understand others' emotional needs and tend to attract partners who appreciate your depth and authenticity. However, you may sometimes struggle with balancing your need for independence with your desire for close partnership.\n\nYour approach to love is intense and transformative - you don't do shallow connections well. You need partners who can match your emotional depth and who aren't threatened by your strong personality. You tend to be very protective of those you love and can be quite intuitive about their needs, sometimes knowing what they need before they do.\n\nThe key to successful relationships for you lies in learning to communicate your needs clearly while also giving your partners space to be themselves. You have the capacity for profound, healing relationships that transform both you and your partner, but this requires ongoing work on maintaining healthy boundaries and avoiding the tendency to either over-give or become too controlling.",
            
            'career' => "Your career path is likely to involve some form of creative leadership, healing, or transformational work where you can use your natural ability to inspire and guide others. You have excellent instincts for understanding what people need and the vision to see how things could be improved or transformed. Fields that might appeal to you include counseling, creative arts, entrepreneurship, healing professions, or any work that allows you to be both independent and impactful.\n\nYou work best in environments that allow for creativity, autonomy, and meaningful impact. Traditional corporate structures might feel restrictive unless you can find a way to innovate within them. You have natural business instincts and the ability to see opportunities that others might miss, making you well-suited for entrepreneurial ventures.\n\nSuccess comes most naturally when you can align your work with your values and when you feel you're making a real difference. You need work that engages both your mind and your heart, and you tend to excel when given the freedom to develop your own methods and approaches. The key is finding or creating opportunities that honor your need for both security and creative expression."
        ];
        
        return $mockResponses[$category] ?? $this->getGenericMockResponse();
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

        if (str_contains($this->apiEndpoint, 'openai.com')) {
            return 'OpenAI ' . ($this->aiConfig['model'] ?? 'gpt-4o-mini');
        }

        if (str_contains($this->apiEndpoint, 'anthropic.com')) {
            return 'Anthropic ' . ($this->aiConfig['model'] ?? 'claude-sonnet-4-20250514');
        }

        // Default to Ollama
        $model = $this->aiConfig['model'] ?? $_ENV['OLLAMA_MODEL'] ?? 'llama3.1';
        return 'Ollama ' . $model;
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