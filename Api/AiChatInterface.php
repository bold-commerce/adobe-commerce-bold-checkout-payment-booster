<?php

namespace Bold\CheckoutPaymentBooster\Api;

use Bold\CheckoutPaymentBooster\Api\Data\AiChatResponseInterface;

interface AiChatInterface
{
    /**
     * Process AI chat message securely
     *
     * @param string $message
     * @param string|null $cartId
     * @return \Bold\CheckoutPaymentBooster\Api\Data\AiChatResponseInterface
     */
    public function processMessage(string $message, ?string $cartId = null): AiChatResponseInterface;
} 