<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Order;

use Bold\CheckoutPaymentBooster\Model\ResourceModel\Order\OrderExtensionData;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Retrieve order public id.
 */
class GetOrderPublicId
{
    /**
     * @var OrderExtensionDataFactory
     */
    private $orderExtensionDataFactory;

    /**
     * @var OrderExtensionData
     */
    private $orderExtensionData;

    /**
     * @param OrderExtensionDataFactory $orderExtensionDataFactory
     * @param OrderExtensionData $orderExtensionDataResource
     */
    public function __construct(
        OrderExtensionDataFactory  $orderExtensionDataFactory,
        OrderExtensionData $orderExtensionDataResource
    ) {
        $this->orderExtensionDataFactory = $orderExtensionDataFactory;
        $this->orderExtensionData = $orderExtensionDataResource;
    }

    /**
     * Retrieve order public id.
     *
     * @param OrderInterface $order
     * @return string
     * @throws LocalizedException
     */
    public function execute(OrderInterface $order): string
    {
        $orderExtensionData = $this->orderExtensionDataFactory->create();
        $this->orderExtensionData->load($orderExtensionData, $order->getId(), OrderExtensionData::ORDER_ID);
        if (!$orderExtensionData->getPublicId()) {
            throw new LocalizedException(__('Order public id is not set.'));
        }

        return $orderExtensionData->getPublicId();
    }
}
