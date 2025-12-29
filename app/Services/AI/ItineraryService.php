<?php

namespace App\Services\AI;

use App\Http\Requests\AI\ItineraryRequest;
use App\Models\AiSession;
use App\Models\AiMessage;
use App\Models\AiUsage;
use App\Services\AI\DTOs\ChatCompletionRequest;
use App\Services\AI\DTOs\ChatMessage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

/**
 * Itinerary Service
 *
 * Core service for generating multi-day beach itineraries.
 *
 * Source: docs/ai/implementation/PHASE_1_PROPOSAL.md
 */
class ItineraryService
{
    public function __construct(
        private readonly ItineraryContextBuilderService $contextBuilder,
        private readonly LLMService $llmService,
        private readonly LoggingService $loggingService,
    ) {}

    /**
     * Generate itinerary.
     *
     * @param ItineraryRequest $request
     * @param string $requestId
     * @return array Itinerary response
     */
    public function generate(ItineraryRequest $request, string $requestId): array
    {
        $startTime = microtime(true);
        $user = $request->user();
        $tier = $user ? 'basic' : 'free'; // Simplified tier detection

        // Extract request data
        $destination = $request->input('destination');
        $durationDays = $request->input('duration_days');
        $preferences = $request->input('preferences', []);
        $constraints = $request->input('constraints', []);
        $locale = $request->input('locale', app()->getLocale());
        $sessionId = $request->input('session_id');

        // Get or create session
        $session = $this->getOrCreateSession($sessionId, $user);

        // Fetch candidate beaches
        $beaches = $this->contextBuilder->fetchCandidateBeaches(
            $destination,
            $preferences,
            30, // Fetch up to 30 candidate beaches
            $locale
        );

        // Build context text for LLM
        $contextText = $this->contextBuilder->buildContextText(
            $beaches,
            $destination,
            $durationDays,
            $preferences
        );

        // Build system prompt
        $systemPrompt = $this->buildSystemPrompt($locale);

        // Build user prompt
        $userPrompt = $this->buildUserPrompt(
            $destination,
            $durationDays,
            $preferences,
            $constraints,
            $contextText
        );

        // Build messages for LLM
        $messages = [
            ChatMessage::system($systemPrompt),
            ChatMessage::user($userPrompt),
        ];

        // Call LLM
        $llmRequest = new ChatCompletionRequest(
            messages: $messages,
            model: config('ai.llm.default_model'),
            temperature: 0.7,
            maxTokens: 3000, // Higher limit for itineraries
        );

        $llmResponse = $this->llmService->chatCompletion($llmRequest);

        // Parse and validate LLM response
        $parsedItinerary = $this->parseLLMResponse($llmResponse->content);

        // Add metadata
        $parsedItinerary['itinerary_id'] = (string) Str::uuid();
        $parsedItinerary['session_id'] = $session->id;
        $parsedItinerary['metadata'] = [
            'request_id' => $requestId,
            'tier' => $tier,
            'tokens_used' => $llmResponse->totalTokens,
            'model' => $llmResponse->model,
            'response_time_ms' => (int) round((microtime(true) - $startTime) * 1000),
            'cached' => false,
        ];

        // Save to database (includes usage tracking)
        $this->saveItineraryToDatabase(
            $session,
            $request,
            $parsedItinerary,
            $llmResponse
        );

        return $parsedItinerary;
    }

    /**
     * Build system prompt for itinerary generation.
     */
    private function buildSystemPrompt(string $locale): string
    {
        $prompt = "You are an expert beach trip planner for Beachrex, a platform with 2,445+ beaches across Croatia, Slovenia, and Montenegro.\n\n";
        $prompt .= "Your role is to create optimized multi-day beach itineraries based on user preferences.\n\n";
        $prompt .= "CRITICAL RULES:\n";
        $prompt .= "- Always respond in valid JSON format (no markdown, no code blocks)\n";
        $prompt .= "- Only recommend beaches from the CANDIDATE BEACHES list provided\n";
        $prompt .= "- Include 2-6 items per day (at least 1-2 beach items per day)\n";
        $prompt .= "- Use transparent language: 'frequently viewed' or 'based on visitor interest' (NOT 'best rated' or 'highest rated')\n";
        $prompt .= "- DO NOT claim ratings/reviews unless explicitly provided in beach data\n";
        $prompt .= "- Provide practical logistics (travel distance, parking, accessibility)\n";
        $prompt .= "- Be honest about assumptions (e.g., 'Beach ordering uses visitor interest')\n\n";
        $prompt .= "OUTPUT FORMAT:\n";
        $prompt .= "Return a JSON object with these fields:\n";
        $prompt .= "- title: string (localized itinerary title)\n";
        $prompt .= "- summary: string (2-5 sentences)\n";
        $prompt .= "- destination: object {country_code, region_slug?, city_slug?}\n";
        $prompt .= "- duration_days: integer\n";
        $prompt .= "- days: array of day objects (see below)\n";
        $prompt .= "- assumptions: array of strings (transparent statements about data sources)\n\n";
        $prompt .= "Each day object:\n";
        $prompt .= "- day_number: integer (1-indexed)\n";
        $prompt .= "- theme: string (short theme)\n";
        $prompt .= "- base_area: string (base city/area)\n";
        $prompt .= "- items: array of item objects (2-6 items)\n";
        $prompt .= "- logistics: object {estimated_travel_km?, parking_notes?, accessibility_notes?}\n\n";
        $prompt .= "Each item object:\n";
        $prompt .= "- type: string (beach|activity|food|viewpoint)\n";
        $prompt .= "- time_block: string (morning|afternoon|evening)\n";
        $prompt .= "- beach: object {id, name, slug, city, country_code, surface, facilities, distance_km: null} (required if type=beach)\n";
        $prompt .= "- notes: string (1-3 sentences)\n";
        $prompt .= "- why: string (short justification)\n\n";
        $prompt .= "Respond in {$locale} language.\n";

        return $prompt;
    }

    /**
     * Build user prompt for itinerary generation.
     */
    private function buildUserPrompt(
        array $destination,
        int $durationDays,
        array $preferences,
        array $constraints,
        string $contextText
    ): string {
        $prompt = "Generate a {$durationDays}-day beach itinerary for:\n\n";
        $prompt .= "DESTINATION:\n";
        $prompt .= "- Country: {$destination['country_code']}\n";
        if (!empty($destination['region_slug'])) {
            $prompt .= "- Region: {$destination['region_slug']}\n";
        }
        if (!empty($destination['city_slug'])) {
            $prompt .= "- City: {$destination['city_slug']}\n";
        }
        $prompt .= "\n";

        $prompt .= "PREFERENCES:\n";
        $prompt .= "- Pace: " . ($preferences['pace'] ?? 'balanced') . "\n";
        if (!empty($preferences['interests'])) {
            $prompt .= "- Interests: " . implode(', ', $preferences['interests']) . "\n";
        }
        if (!empty($preferences['must_have_facilities'])) {
            $prompt .= "- Required facilities: " . implode(', ', $preferences['must_have_facilities']) . "\n";
        }
        $prompt .= "\n";

        $prompt .= "CONSTRAINTS:\n";
        $prompt .= "- Max travel per day: " . ($constraints['max_travel_km_per_day'] ?? 80) . " km\n";
        $prompt .= "- Avoid ferries: " . ($constraints['avoid_ferries'] ? 'Yes' : 'No') . "\n";
        $prompt .= "\n";

        $prompt .= $contextText;

        $prompt .= "\nGenerate the itinerary now in valid JSON format.";

        return $prompt;
    }

    /**
     * Parse LLM response and validate structure.
     */
    private function parseLLMResponse(string $content): array
    {
        // Remove markdown code blocks if present
        $content = preg_replace('/```json\s*/', '', $content);
        $content = preg_replace('/```\s*$/', '', $content);
        $content = trim($content);

        // Decode JSON
        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON response from LLM: ' . json_last_error_msg());
        }

        // Validate required fields
        $requiredFields = ['title', 'summary', 'destination', 'duration_days', 'days', 'assumptions'];
        foreach ($requiredFields as $field) {
            if (!isset($decoded[$field])) {
                throw new \Exception("Missing required field in LLM response: {$field}");
            }
        }

        // Validate days array
        if (!is_array($decoded['days']) || empty($decoded['days'])) {
            throw new \Exception('Invalid days array in LLM response');
        }

        return $decoded;
    }

    /**
     * Get or create AI session.
     */
    private function getOrCreateSession(?string $sessionId, $user): AiSession
    {
        if ($sessionId) {
            $session = AiSession::find($sessionId);
            if ($session) {
                return $session;
            }
        }

        // Create new session
        return AiSession::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user?->id,
            'tier' => $user ? 'basic' : 'free',
            'locale' => app()->getLocale(),
        ]);
    }

    /**
     * Save itinerary to database.
     */
    private function saveItineraryToDatabase(
        AiSession $session,
        ItineraryRequest $request,
        array $itinerary,
        $llmResponse
    ): void {
        // Save user request as message
        AiMessage::create([
            'session_id' => $session->id,
            'role' => 'user',
            'content' => json_encode($request->validated()),
            'intent' => 'itinerary',
            'context' => [
                'destination' => $request->input('destination'),
                'duration_days' => $request->input('duration_days'),
            ],
            'tokens_input' => $llmResponse->inputTokens,
            'tokens_output' => 0,
            'tokens_total' => $llmResponse->inputTokens,
        ]);

        // Save assistant response as message
        AiMessage::create([
            'session_id' => $session->id,
            'role' => 'assistant',
            'content' => json_encode($itinerary),
            'intent' => 'itinerary',
            'context' => [
                'itinerary_id' => $itinerary['itinerary_id'],
            ],
            'tokens_input' => 0,
            'tokens_output' => $llmResponse->outputTokens,
            'tokens_total' => $llmResponse->outputTokens,
        ]);

        // Track usage
        \App\Models\AiUsage::create([
            'user_id' => $session->user_id,
            'session_id' => $session->id,
            'tier' => $session->tier,
            'event_type' => 'itinerary',
            'llm_provider' => 'openai',
            'llm_model' => $llmResponse->model,
            'tokens_input' => $llmResponse->inputTokens,
            'tokens_output' => $llmResponse->outputTokens,
            'tokens_total' => $llmResponse->totalTokens,
            'cost_usd' => $llmResponse->costUsd,
            'latency_ms' => $itinerary['metadata']['response_time_ms'] ?? 0,
            'cache_hit' => false,
            'beaches_fetched' => count($itinerary['days'] ?? []),
            'beaches_returned' => count($itinerary['days'] ?? []),
            'context_truncated' => false,
            'locale' => $session->locale,
            'user_agent' => $session->user_agent,
            'ip_address' => $session->ip_address,
            'request_id' => $itinerary['metadata']['request_id'] ?? (string) \Illuminate\Support\Str::uuid(),
        ]);
    }
}

