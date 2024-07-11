<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Observer\Checkout;

use Bold\CheckoutPaymentBooster\Model\InitOrderFromQuote;
use Bold\CheckoutPaymentBooster\Model\Payment\Gateway\Config\CanUseCheckoutValueHandler;
use Exception;
use Magento\Checkout\Model\Session;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

/**
 * Initialize Bold order.
 */
class InitializeBoldOrderObserver implements ObserverInterface
{
    /**
     * @var Session
     */
    private $session;

    /**
     * @var InitOrderFromQuote
     */
    private $initOrderFromQuote;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var CanUseCheckoutValueHandler
     */
    private $canUseCheckout;

    /**
     * @param Session $session
     * @param InitOrderFromQuote $initOrderFromQuote
     * @param LoggerInterface $logger
     * @param CanUseCheckoutValueHandler $canUseCheckout
     */
    public function __construct(
        Session $session,
        InitOrderFromQuote $initOrderFromQuote,
        LoggerInterface $logger,
        CanUseCheckoutValueHandler $canUseCheckout
    ) {
        $this->session = $session;
        $this->initOrderFromQuote = $initOrderFromQuote;
        $this->logger = $logger;
        $this->canUseCheckout = $canUseCheckout;
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer): void
    {
        $quote = $this->session->getQuote();
        $this->session->setBoldCheckoutData(null);
        try {
            if (!$this->canUseCheckout->handle([], (int)$quote->getStoreId())) {
                return;
            }
            $checkoutData = $this->initOrderFromQuote->init($quote);
            $this->session->setBoldCheckoutData($checkoutData);
        } catch (Exception $exception) {
            $this->logger->critical($exception);
        }
    }
}
