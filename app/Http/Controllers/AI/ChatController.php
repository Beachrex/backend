<?php

namespace App\Http\Controllers\AI;

use App\Events\AI\AiRequestCompleted;
use App\Events\AI\AiRequestFailed;
use App\Http\Controllers\Controller;
use App\Http\Requests\AI\ChatRequest;
use App\Models\AiMessage;
use App\Models\AiSession;
use App\Models\AiUsage;
use App\Services\AI\ContextBuilderService;
use App\Services\AI\DTOs\ChatCompletionRequest;
use App\Services\AI\DTOs\ChatMessage;
use App\Services\AI\LLMService;
use App\Services\AI\LoggingService;
use App\Services\AI\PlanEnforcementService;
use Illuminate\Http\JsonResponse;
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

        // Detect intent (simple keyword-based for MVP)
        $intent = $this->detectIntent($userMessage);

        // Check plan enforcement for paid features
        $enforcementCheck = $this->checkPlanEnforcement($request->user(), $intent, $userMessage);
        if ($enforcementCheck !== null) {
            return $enforcementCheck;
        }

        // Build context
        $context = $this->buildContext($request, $session, $intent);

        // Get conversation history (last 5 messages)
        $conversationHistory = $this->getConversationHistory($session);

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

            // Save user message
            $this->saveMessage($session, 'user', $userMessage, $intent, $context, $llmResponse->inputTokens, 0);

            // Save assistant message
            $this->saveMessage($session, 'assistant', $parsedResponse['message'], $intent, $parsedResponse, 0, $llmResponse->outputTokens);

            // Update session
            $session->increment('messages_count', 2);
            $session->increment('tokens_used', $llmResponse->totalTokens);
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
                    'tokens_used' => $llmResponse->totalTokens,
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
        if (str_contains($message, 'wheelchair') ||
            str_contains($message, 'accessible') ||
            str_contains($message, 'disability') ||
            str_contains($message, 'accessibility')) {
            return 'accessibility_query';
        }

        // Popularity query (does NOT require location)
        if (str_contains($message, 'popular') ||
            str_contains($message, 'top') ||
            str_contains($message, 'best') ||
            str_contains($message, 'most viewed') ||
            str_contains($message, 'frequently visited')) {
            return 'popularity_query';
        }

        // Proximity query (REQUIRES location)
        if (str_contains($message, 'near me') ||
            str_contains($message, 'nearby') ||
            str_contains($message, 'closest')) {
            return 'proximity_query';
        }

        // Weather query
        if (str_contains($message, 'weather') ||
            str_contains($message, 'temperature') ||
            str_contains($message, 'forecast')) {
            return 'weather_query';
        }

        // Beach comparison
        if (str_contains($message, 'compare') ||
            str_contains($message, 'vs') ||
            str_contains($message, 'versus') ||
            str_contains($message, 'difference')) {
            return 'beach_comparison';
        }

        // Beach search (default)
        if (str_contains($message, 'beach') ||
            str_contains($message, 'find') ||
            str_contains($message, 'show') ||
            str_contains($message, 'search')) {
            return 'beach_search';
        }

        return 'general_question';
    }

    /**
     * Check plan enforcement for paid features.
     */
    private function checkPlanEnforcement($user, string $intent, string $message): ?JsonResponse
    {
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
     */
    private function buildContext(ChatRequest $request, AiSession $session, string $intent): array
    {
        $location = $request->input('context.location');
        $preferences = $request->input('context.preferences', []);

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

        // Add filters from preferences
        if (!empty($preferences['surface_types'])) {
            $params['filters']['surface'] = $preferences['surface_types'];
        }

        if (!empty($preferences['facilities'])) {
            $params['filters']['facilities'] = $preferences['facilities'];
        }

        return $this->contextBuilder->buildContext($params);
    }

    /**
     * Get conversation history (last 5 messages).
     */
    private function getConversationHistory(AiSession $session): array
    {
        return $session->messages()
            ->orderBy('created_at', 'desc')
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
        string $intent
    ): void {
        // Create usage record in database
        AiUsage::create([
            'user_id' => $user?->id,
            'session_id' => $session->id,
            'tier' => $session->tier,
            'event_type' => 'chat',
            'llm_provider' => 'openai',
            'llm_model' => $llmResponse->model,
            'tokens_input' => $llmResponse->inputTokens,
            'tokens_output' => $llmResponse->outputTokens,
            'tokens_total' => $llmResponse->totalTokens,
            'cost_usd' => $llmResponse->costUsd,
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
