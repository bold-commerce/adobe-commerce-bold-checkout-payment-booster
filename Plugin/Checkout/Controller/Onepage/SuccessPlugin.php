<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Plugin\Checkout\Controller\Onepage;

use Bold\CheckoutPaymentBooster\Api\MagentoQuoteBoldOrderRepositoryInterface;
use Bold\CheckoutPaymentBooster\Model\Order\CheckTransactions;
use Closure;
use Magento\Checkout\Controller\Onepage\Success;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Last-line-of-defense: verify Bold payment was authorized before showing the success page.
 */
class SuccessPlugin
{
    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var MagentoQuoteBoldOrderRepositoryInterface
     */
    private $magentoQuoteBoldOrderRepository;

    /**
     * @var CheckTransactions
     */
    private $checkTransactions;

    /**
     * @var RedirectFactory
     */
    private $redirectFactory;

    /**
     * @var MessageManagerInterface
     */
    private $messageManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Constructor method.
     *
     * @param CheckoutSession $checkoutSession The current checkout session.
     * @param MagentoQuoteBoldOrderRepositoryInterface $magentoQuoteBoldOrderRepository The repository for Magento Quote Bold orders.
     * @param CheckTransactions $checkTransactions The service for checking transactions.
     * @param RedirectFactory $redirectFactory Factory for creating redirect responses.
     * @param MessageManagerInterface $messageManager Manager for displaying messages.
     * @param LoggerInterface $logger Logger for recording application events.
     *
     * @return void
     */
    public function __construct(
        CheckoutSession $checkoutSession,
        MagentoQuoteBoldOrderRepositoryInterface $magentoQuoteBoldOrderRepository,
        CheckTransactions $checkTransactions,
        RedirectFactory $redirectFactory,
        MessageManagerInterface $messageManager,
        LoggerInterface $logger
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->magentoQuoteBoldOrderRepository = $magentoQuoteBoldOrderRepository;
        $this->checkTransactions = $checkTransactions;
        $this->redirectFactory = $redirectFactory;
        $this->messageManager = $messageManager;
        $this->logger = $logger;
    }

    /**
     * Intercept the success page, redirect to cart if authorization cannot be confirmed.
     *
     * Decision tree:
     *   1. No last real order → not our concern → proceeds.
     *   2. No bold_booster_magento_quote_bold_order row → not a Bold order → proceeds.
     *   3. Row exists, bold auth_full_at IS set, and Magento AUTH txn exists → proceed.
     *   4. Any other combination → redirect to cart and add a critical log.
     *
     * @param Success $subject
     * @param Closure $proceed
     * @return ResultInterface
     */
    public function aroundExecute(Success $subject, Closure $proceed): ResultInterface
    {
        $order = $this->checkoutSession->getLastRealOrder();

        if (!$order || !$order->getId()) {
            return $proceed();
        }

        $quoteId = (string) $order->getQuoteId();

        if (!$quoteId) {
            return $proceed();
        }

        try {
            $relation = $this->magentoQuoteBoldOrderRepository->getByQuoteId($quoteId);
        } catch (NoSuchEntityException $e) {
            // No Bold relation record → not a Bold order → nothing to check.
            return $proceed();
        }

        // Check 1: Bold lifecycle table.
        $boldAuthRecorded = $relation->getSuccessfulAuthFullAt() !== null;

        // Check 2: Magento standard sales_payment_transaction table.
        $magentoTxnExists = $this->checkTransactions->hasAuthTransaction($order);

        if ($boldAuthRecorded && $magentoTxnExists) {
            return $proceed();
        }

        $this->logger->critical(
            sprintf(
                'Bold SuccessPlugin: authorization check failed for order %s (quote %s, Bold order %s). '
                . 'bold_auth_full_at=%s | magento_auth_txn=%s | payment_method=%s. '
                . 'Redirecting to cart.',
                $order->getIncrementId(),
                $quoteId,
                $relation->getBoldOrderId() ?? 'unknown',
                $boldAuthRecorded ? 'SET' : 'MISSING',
                $magentoTxnExists ? 'EXISTS' : 'MISSING',
                $order->getPayment() ? $order->getPayment()->getMethod() : 'unknown'
            )
        );

        $this->messageManager->addErrorMessage(
            'Your payment could not be authorized. Please review your payment details.'
        );

        $redirect = $this->redirectFactory->create();

        return $redirect->setPath('checkout/cart');
    }
}
