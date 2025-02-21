<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\ViewModel;

use Bold\CheckoutPaymentBooster\Model\CheckoutData;
use Exception;
use Magento\Checkout\Model\CompositeConfigProvider;
use Magento\Checkout\Model\Session;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Psr\Log\LoggerInterface;

class ExpressPay implements ArgumentInterface
{
    /**
     * @var CompositeConfigProvider
     */
    private $configProvider;

    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var CheckoutData
     */
    private $checkoutData;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param CompositeConfigProvider $configProvider
     * @param Session $checkoutSession
     * @param CheckoutData $checkoutData
     * @param LoggerInterface $logger
     */
    public function __construct(
        CompositeConfigProvider $configProvider,
        Session $checkoutSession,
        CheckoutData $checkoutData,
        LoggerInterface $logger
    ) {
        $this->configProvider = $configProvider;
        $this->checkoutSession = $checkoutSession;
        $this->checkoutData = $checkoutData;
        $this->logger = $logger;
    }

    /**
     * Initialize checkout data and return the config.
     *
     * @return array
     */
    public function getCheckoutConfig(): array
    {
        try {
            $quote = $this->checkoutSession->getQuote();
            if (!$quote->getId()) {
                $quote->save();
            }
            $this->checkoutData->initCheckoutData();
            return $this->configProvider->getConfig();
        } catch (Exception $e) {
            $this->logger->error('ExpressPay: ' . $e->getMessage());
            return [];
        }
    }
}
