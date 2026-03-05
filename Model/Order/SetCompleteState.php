<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Order;

use Bold\CheckoutPaymentBooster\Api\MagentoQuoteBoldOrderRepositoryInterface;
use Bold\CheckoutPaymentBooster\Model\Http\BoldClient;
use Bold\CheckoutPaymentBooster\Model\Order\CheckTransactions;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

/**
 * Mark order as completed.
 */
class SetCompleteState
{
    private const STATE_URL = 'checkout_sidekick/{{shopId}}/order/%s/state';

    /**
     * @var BoldClient
     */
    private $client;

    /**
     * @var GetOrderPublicIdByOrderId
     */
    private $getOrderPublicId;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /** @var MagentoQuoteBoldOrderRepositoryInterface */
    private $magentoQuoteBoldOrderRepository;

    /** @var CheckTransactions  */
    private $checkTransactions;

    /**
     * @param BoldClient $client
     * @param GetOrderPublicIdByOrderId $getOrderPublicId
     * @param LoggerInterface $logger
     * @param MagentoQuoteBoldOrderRepositoryInterface $magentoQuoteBoldOrderRepository
     * @param CheckTransactions $checkTransactions
     */
    public function __construct(
        BoldClient                $client,
        GetOrderPublicIdByOrderId $getOrderPublicId,
        LoggerInterface           $logger,
        MagentoQuoteBoldOrderRepositoryInterface $magentoQuoteBoldOrderRepository,
        CheckTransactions $checkTransactions
    ) {
        $this->checkTransactions = $checkTransactions;
        $this->client = $client;
        $this->getOrderPublicId = $getOrderPublicId;
        $this->logger = $logger;
        $this->magentoQuoteBoldOrderRepository = $magentoQuoteBoldOrderRepository;
    }

    /**
     * Mark order as completed.
     *
     * @param OrderInterface&Order $order
     * @return void
     * @throws LocalizedException
     */
    public function execute(OrderInterface $order): void
    {
        $quoteId = (string) $order->getQuoteId();
        if ($quoteId) {
            // Check 1 — relation record must exist.
            if (!$this->checkTransactions->hasRelationRecord($quoteId)) {
                throw new LocalizedException(
                    __(
                        'Cannot set complete state: no Bold order relation record found for quote %1.',
                        $quoteId
                    )
                );
            }

            // Check 2 — Bold lifecycle table must show a successful authorization.
            if (!$this->checkTransactions->getAuthTransactionFromLifecycle($quoteId)) {
                throw new LocalizedException(
                    __(
                        'Cannot set complete state: payment authorization has not been recorded '
                        . 'in the Bold lifecycle table for order %1 (quote %2).',
                        $order->getIncrementId(),
                        $quoteId
                    )
                );
            }

            // Check 3 — Magento sales_payment_transaction must contain an AUTH row.
            if (!$this->checkTransactions->hasAuthTransaction($order)) {
                throw new LocalizedException(
                    __(
                        'Cannot set complete state: no AUTH transaction found in sales_payment_transaction '
                        . 'for order %1.',
                        $order->getIncrementId()
                    )
                );
            }
        }

        $websiteId = (int)$order->getStore()->getWebsiteId();
        $publicOrderId = $this->getOrderPublicId->execute((int)$order->getEntityId());
        $url = sprintf(self::STATE_URL, $publicOrderId);
        $params = [
            'state' => 'order_complete',
            'platform_order_id' => $order->getEntityId(),
            'platform_friendly_id' => $order->getIncrementId()
        ];
        $response = $this->client->put($websiteId, $url, $params);
        if ($response->getStatus() !== 201) {
            $this->logger->error(__('Failed to set complete state for order with id="%1"', $order->getEntityId()));
            return;
        }
        $quoteId = $order->getQuoteId();
        $this->magentoQuoteBoldOrderRepository->saveStateAt((string) $quoteId);
    }
}
