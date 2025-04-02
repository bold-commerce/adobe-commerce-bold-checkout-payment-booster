<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model;

use Bold\CheckoutPaymentBooster\Model\Order\OrderExtensionData;
use Bold\CheckoutPaymentBooster\Model\Order\OrderExtensionDataFactory;
use Bold\CheckoutPaymentBooster\Model\ResourceModel\Order\OrderExtensionData as OrderExtensionDataResource;
use Magento\Framework\Exception\AlreadyExistsException;

class OrderExtensionDataRepository
{
    /**
     * @var OrderExtensionDataFactory
     */
    private $orderExtensionDataFactory;

    /**
     * @var OrderExtensionDataResource
     */
    private $orderExtensionDataResource;

    /**
     * @param OrderExtensionDataFactory $orderExtensionDataFactory
     * @param OrderExtensionDataResource $orderExtensionDataResource
     */
    public function __construct(
        OrderExtensionDataFactory $orderExtensionDataFactory,
        OrderExtensionDataResource $orderExtensionDataResource
    ) {
        $this->orderExtensionDataFactory = $orderExtensionDataFactory;
        $this->orderExtensionDataResource = $orderExtensionDataResource;
    }

    /**
     * Get OrderExtensionData by order id.
     *
     * @param int $orderId
     * @return OrderExtensionData
     */
    public function getByOrderId(int $orderId): OrderExtensionData
    {
        return $this->getByField(OrderExtensionDataResource::ORDER_ID, (string)$orderId);
    }

    /**
     * Get OrderExtensionData by public order id.
     *
     * @param string $publicOrderId
     * @return OrderExtensionData
     */
    public function getByPublicOrderId(string $publicOrderId): OrderExtensionData
    {
        return $this->getByField(OrderExtensionDataResource::PUBLIC_ID, $publicOrderId);
    }

    /**
     * Get OrderExtensionData by field.
     *
     * @param string $field
     * @param string $value
     * @return OrderExtensionData
     */
    private function getByField(string $field, string $value): OrderExtensionData
    {
        $orderExtensionData = $this->orderExtensionDataFactory->create();
        $this->orderExtensionDataResource->load(
            $orderExtensionData,
            $value,
            $field
        );

        return $orderExtensionData;
    }

    /**
     * Save OrderExtensionData.
     *
     * @param OrderExtensionData $orderExtensionData
     * @return void
     * @throws AlreadyExistsException
     */
    public function save(OrderExtensionData $orderExtensionData)
    {
        $this->orderExtensionDataResource->save($orderExtensionData);
    }
}
