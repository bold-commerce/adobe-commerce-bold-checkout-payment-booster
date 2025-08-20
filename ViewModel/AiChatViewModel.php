<?php

namespace Bold\CheckoutPaymentBooster\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\UrlInterface;

class AiChatViewModel implements ArgumentInterface
{
    private const AI_CHAT_ENABLED_CONFIG_PATH = 'checkout/bold_checkout_payment_booster/ai_chat_enabled';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        UrlInterface $urlBuilder
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->urlBuilder = $urlBuilder;
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

    /**
     * Get secure AI chat API endpoint URL
     *
     * @return string
     */
    public function getAiChatApiUrl(): string
    {
        return $this->urlBuilder->getUrl('rest/V1/bold/ai-chat/message');
    }
} 