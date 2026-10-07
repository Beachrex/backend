<?php

namespace App\Http\Controllers\AI;

use App\Events\AI\AiRequestCompleted;
use App\Events\AI\AiRequestFailed;
use App\Http\Controllers\Controller;
use App\Http\Requests\AI\ChatRequest;
use App\Models\AiMessage;
use App\Models\AiSession;
use App\Models\AiUsage;
use App\Models\BeachTranslation;
use App\Services\AI\Chat\PlaceResolver;
use App\Services\AI\Chat\QueryUnderstandingService;
use App\Services\AI\ContextBuilderService;
use App\Services\AI\DTOs\ChatCompletionResponse;
use App\Services\AI\ItineraryContextBuilderService;
use App\Services\AI\ItineraryService;
use App\Services\AI\DTOs\ChatCompletionRequest;
use App\Services\AI\DTOs\ChatMessage;
use App\Services\AI\LLMService;
use App\Services\AI\LoggingService;
use App\Services\AI\PlanEnforcementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * AI Chat Controller
 *
 * Handles AI chat interactions following the API contract defined in:
 * docs/ai/architecture/api-contracts.md
 *
 * Enforcement order (from middleware + controller):
 * 1. OptionalAuth (middleware)
 * 2. CircuitBreaker (middleware)
 * 3. RateLimit (middleware)
 * 4. TokenBudget (middleware)
 * 5. PlanEnforcement (controller - feature-specific)
 * 6. LLM call (only after all checks pass)
 */
class ChatController extends Controller
{
    public function __construct(
        private readonly ContextBuilderService $contextBuilder,
        private readonly LLMService $llmService,
        private readonly PlanEnforcementService $planEnforcement,
        private readonly LoggingService $loggingService,
        private readonly QueryUnderstandingService $understanding,
        private readonly PlaceResolver $places,
        private readonly ItineraryContextBuilderService $candidates,
    ) {}

    /**
     * Handle chat request.
     *
     * POST /api/v1/ai/chat
     *
     * @OA\Post(
     *     path="/api/v1/ai/chat",
     *     summary="Send message to AI agent",
     *     description="Send a message to the AI agent and get a response with beach recommendations. Authentication is optional - anonymous users can use free tier features, authenticated users can access paid features.",
     *     operationId="aiChat",
     *     tags={"AI Agent"},
     *     security={{"sanctum": {}}, {}},
     *     @OA\Parameter(
     *         ref="#/components/parameters/AcceptLanguage"
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Chat request with user message and optional context",
     *         @OA\JsonContent(ref="#/components/schemas/AiChatRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response with AI recommendations",
     *         @OA\JsonContent(ref="#/components/schemas/AiChatResponse"),
     *         @OA\Header(
     *             header="X-Request-ID",
     *             description="Unique request identifier for tracking",
     *             @OA\Schema(type="string", format="uuid")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request - Invalid input",
     *         @OA\JsonContent(
     *             ref="#/components/schemas/AiErrorResponse",
     *             example={
     *                 "error": "invalid_request",
     *                 "message": "Message is required"
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=402,
     *         description="Payment Required - Paid feature requested",
     *         @OA\JsonContent(
     *             ref="#/components/schemas/AiPaymentRequiredError",
     *             example={
     *                 "error": "payment_required",
     *                 "message": "Multi-day itineraries require payment",
     *                 "product": {
     *                     "type": "itinerary",
     *                     "duration_days": 7,
     *                     "price": 39,
     *                     "currency": "USD"
     *                 },
     *                 "purchase_url": "https://beachrex.com/purchase/itinerary/xyz789"
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=413,
     *         description="Payload Too Large - Token budget exceeded",
     *         @OA\JsonContent(
     *             ref="#/components/schemas/AiTokenBudgetError",
     *             example={
     *                 "error": "token_budget_exceeded",
     *                 "message": "Your query is too complex for the free tier",
     *                 "estimated_tokens": 5200,
     *                 "max_tokens": 4000,
     *                 "suggestion": "Simplify your query or upgrade to paid tier"
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=429,
     *         description="Too Many Requests - Rate limit exceeded",
     *         @OA\JsonContent(
     *             ref="#/components/schemas/AiRateLimitError",
     *             example={
     *                 "error": "rate_limit_exceeded",
     *                 "message": "You have exceeded the rate limit for free tier",
     *                 "limit": 100,
     *                 "reset_at": "2025-12-22T15:00:00Z",
     *                 "upgrade_url": "https://beachrex.com/pricing"
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             ref="#/components/schemas/AiErrorResponse",
     *             example={
     *                 "error": "internal_error",
     *                 "message": "An unexpected error occurred"
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=503,
     *         description="Service Unavailable - AI service temporarily disabled",
     *         @OA\JsonContent(
     *             ref="#/components/schemas/AiServiceUnavailableError",
     *             example={
     *                 "error": "service_unavailable",
     *                 "message": "AI service is temporarily unavailable due to high costs",
     *                 "retry_after": 3600,
     *                 "fallback_url": "https://beachrex.com/search"
     *             }
     *         )
     *     )
     * )
     *
     * @param ChatRequest $request
     * @return JsonResponse
     */
    public function chat(ChatRequest $request): JsonResponse
    {
        $startTime = microtime(true);
        $requestId = (string) Str::uuid();

        // Get or create session
        $session = $this->getOrCreateSession($request);

        // Check if session is expired
        if ($session->isExpired()) {
            return response()->json([
                'error' => 'session_expired',
                'message' => 'Session has expired. Please start a new conversation.',
            ], 410);
        }

        // Get user message
        $userMessage = $request->input('message');

        // Get conversation history (last 5 messages)
        $conversationHistory = $this->getConversationHistory($session);

        // TASK-003: understand the message in any language (structured LLM extraction);
        // keyword detection is the fallback when that call fails.
        $understood = $this->understanding->understand($userMessage, array_values($conversationHistory));
        $understandingResponse = $this->understanding->lastResponse;
        $intent = $understood['intent'] ?? $this->detectIntent($userMessage);

        // Check plan enforcement for paid features
        $enforcementCheck = $this->checkPlanEnforcement($request->user(), $intent, $userMessage, $understood['days'] ?? null);
        if ($enforcementCheck !== null) {
            return $enforcementCheck;
        }

        // Build context
        $context = $this->buildContext($request, $session, $intent, $understood);
        $context['metadata']['language'] = $understood['language'] ?? $session->locale;

        // Build messages for LLM
        $messages = $this->buildLLMMessages($userMessage, $context, $conversationHistory);

        // Call LLM
        try {
            $llmRequest = new ChatCompletionRequest(
                messages: $messages,
                model: config('ai.llm.default_model'),
                temperature: 0.7,
                maxTokens: 1000,
            );

            $llmResponse = $this->llmService->chatCompletion($llmRequest);

            // Parse and validate LLM response
            $parsedResponse = $this->parseLLMResponse($llmResponse->content);

            // Enrich beaches with full data from context (includes location slugs, main_image, etc.)
            $parsedResponse['beaches'] = $this->enrichBeaches($parsedResponse['beaches'], $context);

            // Post-process beaches to ensure distance_km is null for popularity queries without location
            if ($intent === 'popularity_query' && !$request->has('context.location')) {
                $parsedResponse['beaches'] = array_map(function ($beach) {
                    $beach['distance_km'] = null;
                    return $beach;
                }, $parsedResponse['beaches']);
            }

            // TASK-003: report the filters the code actually queried, not what the model claims.
            if ($understood !== null) {
                $parsedResponse['filters_applied'] = $context['metadata']['filters_applied'] ?? null;
            }

            // Ensure filters_applied is null (not []) when empty - matches OpenAPI schema (type="object" nullable)
            if (isset($parsedResponse['filters_applied']) && $parsedResponse['filters_applied'] === []) {
                $parsedResponse['filters_applied'] = null;
            }

            // Save user message
            $this->saveMessage($session, 'user', $userMessage, $intent, $context, $llmResponse->inputTokens, 0);

            // Save assistant message
            $this->saveMessage($session, 'assistant', $parsedResponse['message'], $intent, $parsedResponse, 0, $llmResponse->outputTokens);

            // Update session (both LLM calls count)
            $totalTokens = $llmResponse->totalTokens + ($understandingResponse?->totalTokens ?? 0);
            $session->increment('messages_count', 2);
            $session->increment('tokens_used', $totalTokens);
            $session->update(['last_activity_at' => now()]);

            // Track usage
            $this->trackUsage(
                session: $session,
                user: $request->user(),
                llmResponse: $llmResponse,
                context: $context,
                requestId: $requestId,
                latencyMs: (int) ((microtime(true) - $startTime) * 1000),
                intent: $intent,
                understandingResponse: $understandingResponse,
            );

            // Build response
            return response()->json([
                'session_id' => $session->id,
                'message' => $parsedResponse['message'],
                'intent' => $intent,
                'beaches' => $parsedResponse['beaches'] ?? [],
                'filters_applied' => $parsedResponse['filters_applied'] ?? null,
                'suggestions' => $parsedResponse['suggestions'] ?? [],
                'metadata' => [
                    'tokens_used' => $totalTokens,
                    'understanding' => $understood !== null ? 'llm' : 'fallback',
                    'language' => $context['metadata']['language'],
                    'assumptions' => $context['metadata']['assumptions'] ?? [],
                    'model' => $llmResponse->model,
                    'response_time_ms' => (int) ((microtime(true) - $startTime) * 1000),
                    'cached' => false,
                    'request_id' => $requestId,
                    'location_provided' => $request->has('context.location'),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('AI chat error', [
                'request_id' => $requestId,
                'session_id' => $session->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'internal_error',
                'message' => 'An error occurred while processing your request. Please try again.',
            ], 500);
        }
    }

    /**
     * Get or create session.
     */
    private function getOrCreateSession(ChatRequest $request): AiSession
    {
        $sessionId = $request->input('session_id') ?? $request->header('X-Session-ID');

        if ($sessionId) {
            $session = AiSession::find($sessionId);
            if ($session) {
                return $session;
            }
        }

        // Create new session
        $user = $request->user();
        $tier = $this->planEnforcement->determineTier($user);
        $locale = app()->getLocale();

        return AiSession::create([
            'user_id' => $user?->id,
            'tier' => $tier,
            'locale' => $locale,
            'messages_count' => 0,
            'tokens_used' => 0,
            'ip_address' => hash('sha256', $request->ip()),
            'user_agent' => $request->userAgent(),
            'last_activity_at' => now(),
            'expires_at' => now()->addMinutes(config('ai.session.ttl', 30)),
        ]);
    }

    /**
     * Detect intent from user message.
     *
     * Simple keyword-based detection for MVP.
     * Future: Use LLM for intent classification.
     */
    private function detectIntent(string $message): string
    {
        $message = strtolower($message);

        // Multi-day itinerary (PAID)
        if (preg_match('/\b(\d+)[\s-]*(day|days)\b/i', $message, $matches)) {
            $days = (int) $matches[1];
            if ($days >= 2) {
                return 'itinerary_request';
            }
        }

        // Accessibility query (PAID)
        if (preg_match('/\b(wheelchair|accessible|disability|accessibility)\b/', $message)) {
            return 'accessibility_query';
        }

        // Popularity query (does NOT require location)
        if (preg_match('/\b(popular|top|best|most viewed|frequently visited)\b/', $message)) {
            return 'popularity_query';
        }

        // Proximity query (REQUIRES location)
        if (preg_match('/\b(near me|nearby|closest)\b/', $message)) {
            return 'proximity_query';
        }

        // Weather query
        if (preg_match('/\b(weather|temperature|forecast)\b/', $message)) {
            return 'weather_query';
        }

        // Beach comparison
        if (preg_match('/\b(compare|vs|versus|difference)\b/', $message)) {
            return 'beach_comparison';
        }

        // Beach search (default)
        if (preg_match('/\b(beach|beaches|find|show|search)\b/', $message)) {
            return 'beach_search';
        }

        return 'general_question';
    }

    /**
     * Check plan enforcement for paid features.
     */
    private function checkPlanEnforcement($user, string $intent, string $message, ?int $days = null): ?JsonResponse
    {
        // Days understood from the message in any language (TASK-003) win over the English regex below.
        if ($intent === 'itinerary_request' && $days !== null) {
            return $days >= 2 ? $this->planEnforcement->checkMultiDayItinerary($user, $days) : null;
        }

        // Multi-day itineraries (2+ days) require payment
        if ($intent === 'itinerary_request') {
            if (preg_match('/\b(\d+)[\s-]*(day|days)\b/i', $message, $matches)) {
                $days = (int) $matches[1];
                if ($days >= 2) {
                    return $this->planEnforcement->checkMultiDayItinerary($user, $days);
                }
            }
        }

        // Accessibility reports require payment
        if ($intent === 'accessibility_query') {
            return $this->planEnforcement->checkAccessibilityReport($user);
        }

        return null;
    }

    /**
     * Build context for LLM.
     *
     * With an understood query (TASK-003) the code picks the beaches: place, country, attributes and GPS
     * are real DB filters. Without one (fallback) the pre-TASK-003 behaviour is kept.
     */
    private function buildContext(ChatRequest $request, AiSession $session, string $intent, ?array $understood = null): array
    {
        $location = $request->input('context.location');
        $preferences = $request->input('context.preferences', []);
        $userMessage = $request->input('message');

        $params = [
            'tier' => $session->tier,
            'locale' => $session->locale,
            'include_weather' => false, // Only include weather if explicitly requested
            'intent' => $intent, // Pass intent to context builder
        ];

        // Add location if provided
        if ($location) {
            $params['latitude'] = $location['latitude'];
            $params['longitude'] = $location['longitude'];
            $params['radius'] = $preferences['max_distance_km'] ?? 50;
        }

        if ($understood !== null) {
            [$criteria, $assumptions] = $this->criteriaFrom($understood, $preferences, $params, $session->locale);
            $filtersApplied = null;
            if ($this->shouldFetchCandidates($intent, $criteria, $location !== null)) {
                $limit = $intent === 'popularity_query' ? 3 : ContextBuilderService::MAX_BEACHES[$session->tier] ?? 5;
                $found = $this->candidates->fetchChatCandidates($criteria, $understood['language'] ?? $session->locale, $limit);
                $params['candidates'] = $found['beaches'];
                foreach ($found['relaxed'] as $key) {
                    $criteria[$key] = [];
                    $assumptions[] = "Not enough beaches matched all requested {$key}; that filter was relaxed.";
                }
                $filtersApplied = $this->filtersApplied($criteria);
            }

            $context = $this->contextBuilder->buildContext($params);
            $context['metadata']['filters_applied'] = $filtersApplied;
            $context['metadata']['assumptions'] = $assumptions;

            return $context;
        }

        // Add filters from preferences
        if (!empty($preferences['surface_types'])) {
            $params['filters']['surface'] = $preferences['surface_types'];
        }

        if (!empty($preferences['facilities'])) {
            $params['filters']['facilities'] = $preferences['facilities'];
        }

        // Parse country from message (simple keyword matching for MVP)
        $country = $this->parseCountryFromMessage($userMessage);
        if ($country) {
            $params['filters']['country'] = $country;
        }

        return $this->contextBuilder->buildContext($params);
    }

    /**
     * Understood query + request preferences → candidate criteria, plus assumptions to report.
     *
     * @return array{0: array, 1: string[]}
     */
    private function criteriaFrom(array $understood, array $preferences, array $params, string $locale): array
    {
        $assumptions = [];
        $criteria = [
            'country_code' => $understood['country_code'],
            'beach_types' => $understood['beach_types'],
            'facilities' => $understood['facilities'],
            'user_types' => $understood['user_types'],
            'activities' => $understood['activities'],
            'accessible' => $understood['intent'] === 'accessibility_query',
            'order' => $understood['intent'] === 'popularity_query' ? 'popularity' : 'score',
        ];

        // Structured preferences from the request (UI filters) are merged in.
        foreach ($preferences['surface_types'] ?? [] as $surface) {
            array_push($criteria['beach_types'], ...(ItineraryContextBuilderService::SURFACE_TO_BEACH_TYPES[$surface] ?? []));
        }
        foreach ($preferences['facilities'] ?? [] as $facility) {
            $key = str_replace([' ', '-'], '_', mb_strtolower(trim((string) $facility)));
            if (isset(ItineraryContextBuilderService::FACILITY_TO_INFRASTRUCTURE[$key])) {
                $criteria['facilities'][] = ItineraryContextBuilderService::FACILITY_TO_INFRASTRUCTURE[$key];
            }
        }
        $criteria['beach_types'] = array_values(array_unique($criteria['beach_types']));
        $criteria['facilities'] = array_values(array_unique($criteria['facilities']));

        if ($understood['place'] !== null) {
            $place = $this->places->resolve($understood['place'], $locale);
            if ($place !== null) {
                $criteria["{$place['type']}_id"] = $place['id'];
                $criteria['place'] = $place;
            } else {
                $assumptions[] = "\"{$understood['place']}\" is not a place in the Beachrex database, so it was not used as a filter.";
            }
        }

        if ($understood['beach_names'] !== []) {
            $ids = $this->beachIdsByName($understood['beach_names']);
            if ($ids !== []) {
                $criteria['beach_ids'] = $ids;
            }
        }

        if (isset($params['latitude'], $params['longitude'])) {
            $criteria['latitude'] = (float) $params['latitude'];
            $criteria['longitude'] = (float) $params['longitude'];
            $criteria['radius_km'] = (float) ($params['radius'] ?? 50);
            if ($criteria['order'] !== 'popularity') {
                $criteria['order'] = 'distance';
            }
        }

        return [$criteria, $assumptions];
    }

    /** Proximity without a location stays empty (the model asks for the location), as before. */
    private function shouldFetchCandidates(string $intent, array $criteria, bool $hasLocation): bool
    {
        if ($intent === 'proximity_query' && ! $hasLocation) {
            return false;
        }
        $hasCriteria = ! empty($criteria['country_code']) || ! empty($criteria['place']) || ! empty($criteria['beach_ids'])
            || $criteria['beach_types'] || $criteria['facilities'] || $criteria['user_types'] || $criteria['activities'];

        return $hasLocation || $hasCriteria || $intent !== 'general_question';
    }

    /** filters_applied with the real /beaches parameter names. */
    private function filtersApplied(array $criteria): ?array
    {
        $place = $criteria['place'] ?? null;
        $filters = array_filter([
            'country_code' => $criteria['country_code'] ?? null,
            ($place ? "{$place['type']}_code" : 'place') => $place['code'] ?? null,
            'beach_type' => $criteria['beach_types'] ?: null,
            'location_infrastructure' => array_values(array_unique(array_merge(
                $criteria['facilities'], ! empty($criteria['accessible']) ? ['access_for_disabled'] : []
            ))) ?: null,
            'user_type' => $criteria['user_types'] ?: null,
            'sporting_activities' => $criteria['activities'] ?: null,
        ], fn ($v) => $v !== null);

        return $filters ?: null;
    }

    /** @return int[] active beaches whose translated name or slug matches one of the names */
    private function beachIdsByName(array $names): array
    {
        $slugs = array_map(fn ($n) => Str::slug(Str::ascii($n)), $names);

        return BeachTranslation::query()
            ->whereIn('slug', $slugs)
            ->orWhereIn(DB::raw('lower(name)'), array_map('mb_strtolower', $names))
            ->pluck('beach_id')->unique()->take(5)->values()->all();
    }

    /**
     * Parse country code from user message (simple keyword matching for MVP).
     */
    private function parseCountryFromMessage(string $message): ?string
    {
        $messageLower = strtolower($message);

        // Country keyword mapping
        $countryMap = [
            'croatia' => 'HR',
            'croatian' => 'HR',
            'hrvatska' => 'HR',
            'slovenia' => 'SI',
            'slovenian' => 'SI',
            'slovenija' => 'SI',
            'montenegro' => 'ME',
            'crna gora' => 'ME',
        ];

        foreach ($countryMap as $keyword => $code) {
            if (str_contains($messageLower, $keyword)) {
                return $code;
            }
        }

        return null;
    }

    /**
     * Get conversation history (last 5 messages).
     */
    private function getConversationHistory(AiSession $session): array
    {
        // The user and assistant messages of one turn share a timestamp; id keeps their order.
        return $session->messages()
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get()
            ->reverse()
            ->map(fn($msg) => [
                'role' => $msg->role,
                'content' => $msg->content,
            ])
            ->toArray();
    }

    /**
     * Build messages for LLM.
     */
    private function buildLLMMessages(string $userMessage, array $context, array $history): array
    {
        $messages = [];

        // System prompt
        $systemPrompt = $this->getSystemPrompt($context);
        $messages[] = ChatMessage::system($systemPrompt);

        // Add conversation history
        foreach ($history as $msg) {
            if ($msg['role'] === 'user') {
                $messages[] = ChatMessage::user($msg['content']);
            } elseif ($msg['role'] === 'assistant') {
                $messages[] = ChatMessage::assistant($msg['content']);
            }
        }

        // Add current user message
        $messages[] = ChatMessage::user($userMessage);

        return $messages;
    }

    /**
     * Get system prompt with injected context.
     */
    private function getSystemPrompt(array $context): string
    {
        $locale = $context['metadata']['locale'] ?? 'en';

        // Base system prompt
        $prompt = "You are a helpful beach discovery assistant for Beachrex, a platform with 2,445+ beaches across Croatia, Slovenia, and Montenegro.\n\n";
        $prompt .= "Your role is to help users find the perfect beach based on their preferences.\n\n";
        $language = ItineraryService::LANGUAGE_NAMES[$context['metadata']['language'] ?? $locale] ?? 'English';
        $prompt .= "Write \"message\" and \"suggestions\" in {$language} (the user's language).\n\n";
        $prompt .= "IMPORTANT RULES:\n";
        $prompt .= "- Always respond in valid JSON format\n";
        $prompt .= "- Recommend 1-3 beaches maximum (never more)\n";
        $prompt .= "- Only recommend beaches from the AVAILABLE BEACHES list below\n";
        $prompt .= "- Include match_score (0-1) for each beach based on relevance\n";
        $prompt .= "- Provide 2-3 follow-up suggestions\n\n";

        // Inject context
        $prompt .= $this->contextBuilder->formatContextAsText($context);

        $prompt .= "\n\nOUTPUT FORMAT (strict JSON):\n";
        $prompt .= "{\n";
        $prompt .= "  \"message\": \"Your helpful response message\",\n";
        $prompt .= "  \"beaches\": [\n";
        $prompt .= "    {\n";
        $prompt .= "      \"id\": 123,\n";
        $prompt .= "      \"name\": \"Beach Name\",\n";
        $prompt .= "      \"slug\": \"beach-slug\",\n";
        $prompt .= "      \"country_code\": \"HR\",\n";
        $prompt .= "      \"city\": \"City Name\",\n";
        $prompt .= "      \"surface\": \"sand\",\n";
        $prompt .= "      \"facilities\": [\"parking\", \"showers\"],\n";
        $prompt .= "      \"distance_km\": 12.5,\n";
        $prompt .= "      \"match_score\": 0.95\n";
        $prompt .= "    }\n";
        $prompt .= "  ],\n";
        $prompt .= "  \"filters_applied\": {\"country\": \"HR\", \"facilities\": [\"parking\"]},\n";
        $prompt .= "  \"suggestions\": [\"Show beaches with playgrounds\", \"Which has best weather?\"]\n";
        $prompt .= "}\n";

        return $prompt;
    }

    /**
     * Parse and validate LLM response.
     */
    private function parseLLMResponse(string $content): array
    {
        // Try to extract JSON from response
        $json = $this->extractJSON($content);

        if ($json === null) {
            // Fallback: return safe default response
            return [
                'message' => $content,
                'beaches' => [],
                'filters_applied' => null,
                'suggestions' => [],
            ];
        }

        // Validate and sanitize
        return [
            'message' => $json['message'] ?? 'I found some beaches for you.',
            'beaches' => array_slice($json['beaches'] ?? [], 0, 3), // Max 3 beaches
            'filters_applied' => $json['filters_applied'] ?? null,
            'suggestions' => array_slice($json['suggestions'] ?? [], 0, 3), // Max 3 suggestions
        ];
    }
    /**
     * Enrich beaches from LLM response with full data from context.
     *
     * The LLM only returns basic beach info (id, name, slug, etc.).
     * We need to enrich with location slugs, main_image, and other fields from the context.
     *
     * CRITICAL: Only return beaches that exist in the context (status_id = 1).
     * NEVER use LLM-hallucinated beach data.
     */
    private function enrichBeaches(array $llmBeaches, array $context): array
    {
        // Get beaches from context (these have full data with location slugs)
        $contextBeaches = $context['beaches'] ?? [];

        // Create a map of beach ID => full beach data
        $beachMap = [];
        foreach ($contextBeaches as $beach) {
            $beachMap[$beach['id']] = $beach;
        }

        // Enrich LLM beaches with full data
        $enrichedBeaches = [];
        $hallucinatedBeaches = [];

        foreach ($llmBeaches as $llmBeach) {
            $beachId = $llmBeach['id'] ?? null;

            if ($beachId && isset($beachMap[$beachId])) {
                // Merge LLM beach data with context beach data
                // LLM data takes precedence for match_score
                $enrichedBeach = array_merge(
                    $beachMap[$beachId], // Full data from context
                    $llmBeach // LLM data (includes match_score)
                );
                $enrichedBeaches[] = $enrichedBeach;
            } else {
                // CRITICAL: LLM hallucinated a beach ID that doesn't exist in context
                // This means either:
                // 1. Beach doesn't exist in database
                // 2. Beach has status_id != 1 (inactive)
                // 3. Beach doesn't match filters
                //
                // NEVER use hallucinated data - skip this beach entirely
                $hallucinatedBeaches[] = [
                    'id' => $beachId,
                    'name' => $llmBeach['name'] ?? 'unknown',
                ];

                Log::warning('LLM hallucinated beach not in context', [
                    'beach_id' => $beachId,
                    'beach_name' => $llmBeach['name'] ?? 'unknown',
                    'context_beach_ids' => array_column($contextBeaches, 'id'),
                ]);
            }
        }

        // Log summary if any hallucinations occurred
        if (!empty($hallucinatedBeaches)) {
            Log::warning('LLM hallucinated beaches filtered out', [
                'hallucinated_count' => count($hallucinatedBeaches),
                'hallucinated_beaches' => $hallucinatedBeaches,
                'returned_count' => count($enrichedBeaches),
            ]);
        }

        return $enrichedBeaches;
    }

    /**
     * Extract JSON from LLM response (handles markdown code blocks).
     */
    private function extractJSON(string $content): ?array
    {
        // Try direct JSON decode
        $decoded = json_decode($content, true);
        if ($decoded !== null) {
            return $decoded;
        }

        // Try to extract from markdown code block
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $content, $matches)) {
            $decoded = json_decode($matches[1], true);
            if ($decoded !== null) {
                return $decoded;
            }
        }

        // Try to find JSON object in content
        if (preg_match('/\{.*\}/s', $content, $matches)) {
            $decoded = json_decode($matches[0], true);
            if ($decoded !== null) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * Save message to database.
     */
    private function saveMessage(
        AiSession $session,
        string $role,
        string $content,
        string $intent,
        array $context,
        int $tokensInput,
        int $tokensOutput
    ): void {
        AiMessage::create([
            'session_id' => $session->id,
            'role' => $role,
            'content' => $content,
            'intent' => $intent,
            'context' => $context,
            'tokens_input' => $tokensInput,
            'tokens_output' => $tokensOutput,
            'tokens_total' => $tokensInput + $tokensOutput,
        ]);
    }

    /**
     * Track usage metrics.
     */
    private function trackUsage(
        AiSession $session,
        $user,
        $llmResponse,
        array $context,
        string $requestId,
        int $latencyMs,
        string $intent,
        ?ChatCompletionResponse $understandingResponse = null,
    ): void {
        $extra = $understandingResponse;
        // Create usage record in database
        AiUsage::create([
            'user_id' => $user?->id,
            'session_id' => $session->id,
            'tier' => $session->tier,
            'event_type' => 'chat',
            'llm_provider' => 'openai',
            'llm_model' => $llmResponse->model,
            'tokens_input' => $llmResponse->inputTokens + ($extra?->inputTokens ?? 0),
            'tokens_output' => $llmResponse->outputTokens + ($extra?->outputTokens ?? 0),
            'tokens_total' => $llmResponse->totalTokens + ($extra?->totalTokens ?? 0),
            'cost_usd' => $llmResponse->costUsd + ($extra?->costUsd ?? 0),
            'latency_ms' => $latencyMs,
            'cache_hit' => false,
            'beaches_fetched' => $context['metadata']['beaches_fetched'] ?? 0,
            'beaches_returned' => $context['metadata']['beaches_returned'] ?? 0,
            'context_truncated' => $context['metadata']['context_truncated'] ?? false,
            'locale' => $session->locale,
            'user_agent' => $session->user_agent,
            'ip_address' => $session->ip_address,
            'request_id' => $requestId,
        ]);

        // Build log context
        $logContext = $this->loggingService->buildContext(
            requestId: $requestId,
            sessionId: $session->id,
            tier: $session->tier,
            locale: $session->locale,
            latencyMs: $latencyMs,
            llmResponse: $llmResponse,
            context: $context
        );
        $logContext['intent'] = $intent;

        // Log success
        $this->loggingService->logSuccess($logContext);

        // Fire event for metrics
        event(new AiRequestCompleted(
            requestId: $requestId,
            sessionId: $session->id,
            tier: $session->tier,
            locale: $session->locale,
            latencyMs: $latencyMs,
            tokensInput: $llmResponse->inputTokens,
            tokensOutput: $llmResponse->outputTokens,
            tokensTotal: $llmResponse->totalTokens,
            costUsd: $llmResponse->costUsd,
            contextTruncated: $context['metadata']['context_truncated'] ?? false,
            beachesFetched: $context['metadata']['beaches_fetched'] ?? 0,
            beachesReturned: $context['metadata']['beaches_returned'] ?? 0,
            intent: $intent,
            llmProvider: $llmResponse->provider,
            llmModel: $llmResponse->model,
        ));
    }
}
