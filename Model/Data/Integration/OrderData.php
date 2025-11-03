<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Data\Integration;

use Bold\CheckoutPaymentBooster\Api\Data\Integration\OrderDataInterface;
use Magento\Framework\DataObject;

/**
 * Order data model for place order response.
 */
class OrderData extends DataObject implements OrderDataInterface
{
    public const PLATFORM_ORDER_ID = 'platform_order_id';
    public const PLATFORM_FRIENDLY_ID = 'platform_friendly_id';
    public const ORDER = 'order';

    /**
     * @return string
     */
    public function getPlatformOrderId(): string
    {
        return $this->getData(self::PLATFORM_ORDER_ID);
    }

    /**
     * @param string $platformOrderId
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\OrderDataInterface
     */
    public function setPlatformOrderId(string $platformOrderId): OrderDataInterface
    {
        return $this->setData(self::PLATFORM_ORDER_ID, $platformOrderId);
    }

    /**
     * @return string
     */
    public function getPlatformFriendlyId(): string
    {
        return $this->getData(self::PLATFORM_FRIENDLY_ID);
    }

    /**
     * @param string $platformFriendlyId
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\OrderDataInterface
     */
    public function setPlatformFriendlyId(string $platformFriendlyId): OrderDataInterface
    {
        return $this->setData(self::PLATFORM_FRIENDLY_ID, $platformFriendlyId);
    }

    /**
     * @return \Magento\Sales\Api\Data\OrderInterface
     */
    public function getOrder(): \Magento\Sales\Api\Data\OrderInterface
    {
        return $this->getData(self::ORDER);
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\OrderDataInterface
     */
    public function setOrder(\Magento\Sales\Api\Data\OrderInterface $order): OrderDataInterface
    {
        return $this->setData(self::ORDER, $order);
    }
}

