<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Order;

use Bold\CheckoutPaymentBooster\Api\Data\MagentoQuoteBoldOrderInterface;
use Bold\CheckoutPaymentBooster\Api\MagentoQuoteBoldOrderRepositoryInterface;
use Bold\CheckoutPaymentBooster\Api\MagentoQuoteBoldOrderRepositoryInterfaceFactory;
use Bold\CheckoutPaymentBooster\Model\Http\BoldClient;
use Bold\CheckoutPaymentBooster\Model\MagentoQuoteBoldOrder;
use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
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

    /** @var MagentoQuoteBoldOrderRepositoryInterfaceFactory */
    private $magentoQuoteBoldOrderRepositoryFactory;

    /** @var TimezoneInterface */
    private $timezoneInterface;

    /**
     * @param BoldClient $client
     * @param GetOrderPublicIdByOrderId $getOrderPublicId
     * @param LoggerInterface $logger
     * @param MagentoQuoteBoldOrderRepositoryInterfaceFactory $magentoQuoteBoldOrderRepositoryFactory
     * @param TimezoneInterface $timezoneInterface
     */
    public function __construct(
        BoldClient                $client,
        GetOrderPublicIdByOrderId $getOrderPublicId,
        LoggerInterface           $logger,
        MagentoQuoteBoldOrderRepositoryInterfaceFactory $magentoQuoteBoldOrderRepositoryFactory,
        TimezoneInterface $timezoneInterface
    ) {
        $this->client = $client;
        $this->getOrderPublicId = $getOrderPublicId;
        $this->logger = $logger;
        $this->magentoQuoteBoldOrderRepositoryFactory = $magentoQuoteBoldOrderRepositoryFactory;
        $this->timezoneInterface = $timezoneInterface;
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
        $quoteId = $order->getQuoteId();
        $timestamp = $this->timezoneInterface->date()->format('Y-m-d H:i:s');
        $this->saveStateAt($timestamp, (string) $quoteId);
    }

    /**
     * Save Successful State call at to Bolt Quote Public Order Relation
     *
     * @param string $timestamp
     * @param string $quoteId
     * @return void
     */
    private function saveStateAt(string $timestamp, string $quoteId): void
    {
        /** @var MagentoQuoteBoldOrderRepositoryInterface $repository */
        $repository = $this->magentoQuoteBoldOrderRepositoryFactory->create();
        try {
            /** @var MagentoQuoteBoldOrderInterface&MagentoQuoteBoldOrder $relation */
            $relation = $repository->findOrCreateByQuoteId($quoteId);
            $relation->setQuoteId($quoteId);
            $relation->setSuccessfulStateAt($timestamp);
            $repository->save($relation);
            return;
        } catch (LocalizedException | Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }
}
