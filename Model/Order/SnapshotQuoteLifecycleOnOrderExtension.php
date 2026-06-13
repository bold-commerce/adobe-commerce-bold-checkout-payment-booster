<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Order;

use Bold\CheckoutPaymentBooster\Api\MagentoQuoteBoldOrderRepositoryInterface;
use Bold\CheckoutPaymentBooster\Model\OrderExtensionDataRepository;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Copy quote-level Bold lifecycle timestamps onto the order extension row at place order.
 */
class SnapshotQuoteLifecycleOnOrderExtension
{
    /** @var MagentoQuoteBoldOrderRepositoryInterface */
    private $magentoQuoteBoldOrderRepository;

    /** @var OrderExtensionDataRepository */
    private $orderExtensionDataRepository;

    /**
     * @param MagentoQuoteBoldOrderRepositoryInterface $magentoQuoteBoldOrderRepository
     * @param OrderExtensionDataRepository $orderExtensionDataRepository
     */
    public function __construct(
        MagentoQuoteBoldOrderRepositoryInterface $magentoQuoteBoldOrderRepository,
        OrderExtensionDataRepository $orderExtensionDataRepository
    ) {
        $this->magentoQuoteBoldOrderRepository = $magentoQuoteBoldOrderRepository;
        $this->orderExtensionDataRepository = $orderExtensionDataRepository;
    }

    /**
     * Snapshot hydrate/auth timestamps from the quote relation before order_complete.
     *
     * @param OrderExtensionData $orderExtensionData
     * @param OrderInterface $order
     * @return void
     */
    public function applyPreCompleteSnapshot(OrderExtensionData $orderExtensionData, OrderInterface $order): void
    {
        $relation = $this->getQuoteRelation($order);
        if ($relation === null) {
            return;
        }

        $hydrateAt = $relation->getSuccessfulHydrateAt();
        if ($hydrateAt !== null) {
            $orderExtensionData->setSuccessfulHydrateAt($hydrateAt);
        }

        $authAt = $relation->getSuccessfulAuthFullAt();
        if ($authAt !== null) {
            $orderExtensionData->setSuccessfulAuthFullAt($authAt);
        }
    }

    /**
     * Snapshot order_complete timestamp after state is saved on the quote relation.
     *
     * @param OrderInterface $order
     * @return void
     * @throws AlreadyExistsException
     */
    public function applyStateSnapshot(OrderInterface $order): void
    {
        $orderId = (int)$order->getEntityId();
        if (!$orderId) {
            return;
        }

        $relation = $this->getQuoteRelation($order);
        if ($relation === null) {
            return;
        }

        $stateAt = $relation->getSuccessfulStateAt();
        if ($stateAt === null) {
            return;
        }

        $orderExtensionData = $this->orderExtensionDataRepository->getByOrderId($orderId);
        if (!$orderExtensionData->getOrderId()) {
            return;
        }

        $orderExtensionData->setSuccessfulStateAt($stateAt);
        $this->orderExtensionDataRepository->save($orderExtensionData);
    }

    /**
     * @param OrderInterface $order
     * @return \Bold\CheckoutPaymentBooster\Api\Data\MagentoQuoteBoldOrderInterface|null
     */
    private function getQuoteRelation(OrderInterface $order)
    {
        $quoteId = (int)$order->getQuoteId();
        if (!$quoteId) {
            return null;
        }

        try {
            return $this->magentoQuoteBoldOrderRepository->getByQuoteId($quoteId);
        } catch (NoSuchEntityException $e) {
            return null;
        }
    }
}
