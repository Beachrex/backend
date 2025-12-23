<?php

namespace App\Services\AI\DTOs;

/**
 * Chat Message DTO
 * 
 * Represents a single message in a chat conversation.
 */
class ChatMessage
{
    public function __construct(
        public readonly string $role,    // 'system', 'user', 'assistant'
        public readonly string $content,
        public readonly ?string $name = null,
    ) {}
    
    /**
     * Create a system message.
     */
    public static function system(string $content): self
    {
        return new self('system', $content);
    }
    
    /**
     * Create a user message.
     */
    public static function user(string $content, ?string $name = null): self
    {
        return new self('user', $content, $name);
    }
    
    /**
     * Create an assistant message.
     */
    public static function assistant(string $content): self
    {
        return new self('assistant', $content);
    }
    
    /**
     * Convert to array format for API requests.
     */
    public function toArray(): array
    {
        $message = [
            'role' => $this->role,
            'content' => $this->content,
        ];
        
        if ($this->name !== null) {
            $message['name'] = $this->name;
        }
        
        return $message;
    }
}

