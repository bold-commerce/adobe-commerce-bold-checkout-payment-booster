<?php

namespace Bold\CheckoutPaymentBooster\Api;

use Bold\CheckoutPaymentBooster\Api\Data\AiChatResponseInterface;

interface AiChatInterface
{
    /**
     * Process AI chat message with context
     *
     * @param string $message
     * @param array|null $context
     * @return \Bold\CheckoutPaymentBooster\Api\Data\AiChatResponseInterface
     */
    public function processMessage(string $message, ?array $context = null): AiChatResponseInterface;
} 