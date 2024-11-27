<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Observer\Checkout;

use Bold\CheckoutPaymentBooster\Model\CheckoutData;
use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

/**
 * Initialize Bold order.
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
     * @inheritDoc
     */
    public function execute(Observer $observer): void
    {
        $request = $observer->getEvent()->getRequest();
        if ($request) {
            $observerAction = $request->getFullActionName();
            $this->logger->info($observerAction);
        }

        try {
            $this->checkoutData->initCheckoutData();
        } catch (Exception $exception) {
            $this->logger->critical($exception);
        }
    }
}
