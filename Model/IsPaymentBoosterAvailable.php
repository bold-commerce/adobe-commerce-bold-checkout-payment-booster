<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model;

use Magento\Checkout\Model\Session;

/**
 * Is Payment Booster available service.
 */
class IsPaymentBoosterAvailable
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var Session
     */
    private $session;

    /**
     * @param Config $config
     * @param Session $session
     */
    public function __construct(Config $config, Session $session)
    {
        $this->config = $config;
        $this->session = $session;
    }

    /**
     * Check if Payment Booster is available.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        $websiteId = (int)$this->session->getQuote()->getStore()->getWebsiteId();
        return $this->config->isPaymentBoosterEnabled($websiteId);
    }
}
