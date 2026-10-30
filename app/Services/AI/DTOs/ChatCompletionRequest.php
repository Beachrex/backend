<?php

namespace App\Services\AI\DTOs;

/**
 * Chat Completion Request DTO
 * 
 * Represents a request to the LLM provider for chat completion.
 */
class ChatCompletionRequest
{
    /**
     * @param ChatMessage[] $messages
     */
    public function __construct(
        public readonly array $messages,
        public readonly ?string $model = null,
        public readonly ?float $temperature = null,
        public readonly ?int $maxTokens = null,
        public readonly ?float $topP = null,
        public readonly ?int $n = null,
        public readonly ?array $stop = null,
        public readonly ?string $user = null,
        // e.g. ['type' => 'json_object'] (OpenAI JSON mode)
        public readonly ?array $responseFormat = null,
        // 'openai' | 'anthropic'; null = the configured provider (AI_PROVIDER). Set from AiModelCatalog.
        public readonly ?string $provider = null,
    ) {}
    
    /**
     * Convert messages to array format.
     * 
     * @return array
     */
    public function getMessagesArray(): array
    {
        return array_map(fn(ChatMessage $msg) => $msg->toArray(), $this->messages);
    }
}

