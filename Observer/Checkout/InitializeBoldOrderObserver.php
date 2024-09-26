<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Observer\Checkout;

use Bold\CheckoutPaymentBooster\Model\InitOrderFromQuote;
use Bold\CheckoutPaymentBooster\Model\IsPaymentBoosterAvailable;
use Bold\CheckoutPaymentBooster\Model\ResumeOrderFromQuote;
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
     * @var ResumeOrderFromQuote
     */
    private $resumeOrderFromQuote;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var IsPaymentBoosterAvailable
     */
    private $isPaymentBoosterAvailable;

    /**
     * @param Session $session
     * @param InitOrderFromQuote $initOrderFromQuote
     * @param ResumeOrderFromQuote $resumeOrderFromQuote
     * @param LoggerInterface $logger
     * @param IsPaymentBoosterAvailable $isPaymentBoosterAvailable
     */
    public function __construct(
        Session                   $session,
        InitOrderFromQuote        $initOrderFromQuote,
        ResumeOrderFromQuote      $resumeOrderFromQuote,
        LoggerInterface           $logger,
        IsPaymentBoosterAvailable $isPaymentBoosterAvailable
    ) {
        $this->session = $session;
        $this->initOrderFromQuote = $initOrderFromQuote;
        $this->resumeOrderFromQuote = $resumeOrderFromQuote;
        $this->logger = $logger;
        $this->isPaymentBoosterAvailable = $isPaymentBoosterAvailable;
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer): void
    {
        $quote = $this->session->getQuote();
        $this->session->setBoldCheckoutData(null);
        try {
            if (!$this->isPaymentBoosterAvailable->isAvailable()) {
                return;
            }
            $checkoutData = $this->resumeOrderFromQuote->resume($quote);
            if (!$checkoutData) {
                $checkoutData = $this->initOrderFromQuote->init($quote);
            }
            $this->session->setBoldCheckoutData($checkoutData);
        } catch (Exception $exception) {
            $this->logger->critical($exception);
        }
    }
}
