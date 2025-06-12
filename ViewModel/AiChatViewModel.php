<?php

namespace Bold\CheckoutPaymentBooster\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;

class AiChatViewModel implements ArgumentInterface
{
    private const AI_CHAT_ENABLED_CONFIG_PATH = 'checkout/bold_checkout_payment_booster/ai_chat_enabled';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Check if AI chat is enabled
     *
     * @return bool
     */
    public function isAiChatEnabled(): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::AI_CHAT_ENABLED_CONFIG_PATH,
            ScopeInterface::SCOPE_STORE
        );
    }
} 