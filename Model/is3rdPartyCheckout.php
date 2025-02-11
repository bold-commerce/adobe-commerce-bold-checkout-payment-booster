<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model;

use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Detect 3rd party checkout.
 */
class is3rdPartyCheckout
{
    /**
     * @var ModuleManager
     */
    private $moduleManager;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    public function __construct(
        ModuleManager $moduleManager,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager
    ) {
        $this->moduleManager = $moduleManager;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
    }

    public function get3rdPartyCheckoutName(): string
    {
        if ($this->isAmastyOsc()) {
            return 'Amasty';
        }

        if ($this->isSwissupOsc()) {
            return 'Swissup';
        }

        if ($this->isIOsc()) {
            return 'IOsc';
        }

        return '';
    }

    private function isAmastyOsc(): bool
    {
        return $this->moduleManager->isEnabled('Amasty_Checkout') &&
            $this->scopeConfig->isSetFlag(
                'amasty_checkout/general/enabled',
                ScopeInterface::SCOPE_STORE,
                $this->storeManager->getStore()->getId()
            );
    }

    private function isSwissupOsc(): bool
    {
        return $this->moduleManager->isEnabled('Swissup_Firecheckout') &&
            $this->scopeConfig->isSetFlag(
                'firecheckout/general/enabled',
                ScopeInterface::SCOPE_STORE,
                $this->storeManager->getStore()->getId()
            );
    }

    private function isIOsc(): bool
    {
        return $this->moduleManager->isEnabled('Onestepcheckout_Iosc');
    }
}
