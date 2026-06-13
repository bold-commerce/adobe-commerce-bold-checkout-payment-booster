<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Observer\Checkout;

use Bold\CheckoutPaymentBooster\Model\CheckoutData;
use Bold\CheckoutPaymentBooster\Model\Logger\SessionReuseLogger;
use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

/**
 * Initialize|Refresh Bold order observer.
 */
class InitializeBoldOrderObserver implements ObserverInterface
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var CheckoutData
     */
    private $checkoutData;

    /**
     * @var SessionReuseLogger
     */
    private $sessionReuseLogger;

    /**
     * @param LoggerInterface $logger
     * @param CheckoutData $checkoutData
     * @param SessionReuseLogger $sessionReuseLogger
     */
    public function __construct(
        LoggerInterface $logger,
        CheckoutData $checkoutData,
        SessionReuseLogger $sessionReuseLogger
    ) {
        $this->logger = $logger;
        $this->checkoutData = $checkoutData;
        $this->sessionReuseLogger = $sessionReuseLogger;
    }

    /**
     * Initialize|Refresh Bold order before getting to the checkout page.
     */
    public function execute(Observer $observer): void
    {
        $this->sessionReuseLogger->info('checkout_index: InitializeBoldOrderObserver fired');

        try {
            $this->checkoutData->initCheckoutData();
        } catch (Exception $exception) {
            $this->sessionReuseLogger->warning(
                'checkout_index: initCheckoutData failed',
                ['error' => $exception->getMessage()]
            );
            $this->logger->error('Cannot Initialize Bold Order On Checkout: ' . $exception->getMessage());
        }
    }
}
