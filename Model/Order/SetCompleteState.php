<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Order;

use Bold\CheckoutPaymentBooster\Api\MagentoQuoteBoldOrderRepositoryInterface;
use Bold\CheckoutPaymentBooster\Model\Http\BoldClient;
use Bold\CheckoutPaymentBooster\Model\Logger\SessionReuseLogger;
use Bold\CheckoutPaymentBooster\Model\Order\SnapshotQuoteLifecycleOnOrderExtension;
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

    /** @var SessionReuseLogger */
    private $sessionReuseLogger;

    /** @var SnapshotQuoteLifecycleOnOrderExtension */
    private $snapshotQuoteLifecycleOnOrderExtension;

    /**
     * @param BoldClient $client
     * @param GetOrderPublicIdByOrderId $getOrderPublicId
     * @param LoggerInterface $logger
     * @param MagentoQuoteBoldOrderRepositoryInterface $magentoQuoteBoldOrderRepository
     * @param SessionReuseLogger $sessionReuseLogger
     * @param SnapshotQuoteLifecycleOnOrderExtension $snapshotQuoteLifecycleOnOrderExtension
     */
    public function __construct(
        BoldClient                $client,
        GetOrderPublicIdByOrderId $getOrderPublicId,
        LoggerInterface           $logger,
        MagentoQuoteBoldOrderRepositoryInterface $magentoQuoteBoldOrderRepository,
        SessionReuseLogger $sessionReuseLogger,
        SnapshotQuoteLifecycleOnOrderExtension $snapshotQuoteLifecycleOnOrderExtension
    ) {
        $this->client = $client;
        $this->getOrderPublicId = $getOrderPublicId;
        $this->logger = $logger;
        $this->magentoQuoteBoldOrderRepository = $magentoQuoteBoldOrderRepository;
        $this->sessionReuseLogger = $sessionReuseLogger;
        $this->snapshotQuoteLifecycleOnOrderExtension = $snapshotQuoteLifecycleOnOrderExtension;
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
        $quoteId = (string)$order->getQuoteId();
        $this->magentoQuoteBoldOrderRepository->saveStateAt($quoteId);

        $clearedBoldOrderId = null;

        try {
            $relation = $this->magentoQuoteBoldOrderRepository->getByQuoteId($quoteId);
            $clearedBoldOrderId = $relation->getBoldOrderId();
            $relation->setBoldOrderId('');
            $this->magentoQuoteBoldOrderRepository->save($relation);
        } catch (NoSuchEntityException $e) {
            // Nothing to clear; relation may not exist for this path.
        }

        $this->snapshotQuoteLifecycleOnOrderExtension->applyStateSnapshot($order);

        $this->sessionReuseLogger->info(
            'order_complete: marked quote processed and cleared bold_order_id relation',
            [
                'magento_order_id' => $order->getEntityId(),
                'magento_increment_id' => $order->getIncrementId(),
                'quote_id' => $quoteId,
                'public_order_id' => $publicOrderId,
                'cleared_bold_order_id' => $clearedBoldOrderId,
            ]
        );
    }
}
