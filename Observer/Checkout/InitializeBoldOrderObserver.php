<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Observer\Checkout;

use Bold\CheckoutPaymentBooster\Model\CheckoutData;
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
     * @param LoggerInterface $logger
     * @param CheckoutData $checkoutData
     */
    public function __construct(
        LoggerInterface $logger,
        CheckoutData $checkoutData
    ) {
        $this->logger = $logger;
        $this->checkoutData = $checkoutData;
    }

    /**
     * Initialize|Refresh Bold order before getting to the checkout page.
     */
    public function execute(Observer $observer): void
    {
        try {
            $this->checkoutData->initCheckoutData();
        } catch (Exception $exception) {
            $this->logger->error('Cannot Initialize Bold Order On Checkout: ' . $exception->getMessage());
        }
    }
}
