<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\AI;

use Bold\CheckoutPaymentBooster\Api\AI\Data\ChatMessageInterface;

/**
 * Chat Interface for AI Agent
 */
interface ChatInterface
{
    /**
     * Send message to AI agent and get response
     *
     * @param string $message
     * @param string|null $sessionId
     * @return ChatMessageInterface
     */
    public function sendMessage(string $message, ?string $sessionId = null): ChatMessageInterface;

    /**
     * Get chat history for a session
     *
     * @param string $sessionId
     * @return ChatMessageInterface[]
     */
    public function getChatHistory(string $sessionId): array;

    /**
     * Start a new chat session
     *
     * @return string Session ID
     */
    public function startSession(): string;
} 