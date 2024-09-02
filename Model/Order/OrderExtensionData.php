<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Order;

use Bold\CheckoutPaymentBooster\Model\ResourceModel\Order\OrderExtensionData as OrderExtensionDataResource;
use Magento\Framework\Model\AbstractModel;

/**
 * Bold order data entity.
 */
class OrderExtensionData extends AbstractModel
{
    public const AUTHORITY_NOT_SET = 0;
    public const AUTHORITY_LOCAL = 1;
    public const AUTHORITY_REMOTE = 2;

    /**
     * @inheritDoc
     */
    public function _construct()
    {
        $this->_init(OrderExtensionDataResource::class);
    }

    /**
     * Set order entity id.
     *
     * @param int $orderId
     * @return void
     */
    public function setOrderId(int $orderId): void
    {
        $this->setData(OrderExtensionDataResource::ORDER_ID, $orderId);
    }

    /**
     * Retrieve order id.
     *
     * @return int|null
     */
    public function getOrderId(): ?int
    {
        return $this->getData(OrderExtensionDataResource::ORDER_ID)
            ? (int)$this->getData(OrderExtensionDataResource::ORDER_ID)
            : null;
    }

    /**
     * Set order public id.
     *
     * @param string $publicId
     * @return void
     */
    public function setPublicId(string $publicId): void
    {
        $this->setData(OrderExtensionDataResource::PUBLIC_ID, $publicId);
    }

    /**
     * Retrieve public order id.
     *
     * @return string|null
     */
    public function getPublicId(): ?string
    {
        return $this->getData(OrderExtensionDataResource::PUBLIC_ID);
    }

    /**
     * Set is order using delayed payment capture.
     *
     * @param int $isDelayedCapture
     * @return void
     */
    public function setIsDelayedCapture(int $isDelayedCapture)
    {
        $this->setData(OrderExtensionDataResource::IS_DELAYED_CAPTURE, $isDelayedCapture);
    }

    /**
     * Retrieve is order using delayed payment capture flag.
     *
     * @return int
     */
    public function getIsDelayedCapture(): int
    {
        return (int)$this->getData(OrderExtensionDataResource::IS_DELAYED_CAPTURE);
    }

    /**
     * Get order payment capture authority.
     *
     * @return int
     */
    public function getCaptureAuthority(): int
    {
        return (int)$this->getData(OrderExtensionDataResource::AUTHORITY_CAPTURE);
    }

    /**
     * Get order payment refund authority.
     *
     * @return int
     */
    public function getRefundAuthority(): int
    {
        return (int)$this->getData(OrderExtensionDataResource::AUTHORITY_REFUND);
    }

    /**
     * Get order cancel authority.
     *
     * @return int
     */
    public function getCancelAuthority(): int
    {
        return (int)$this->getData(OrderExtensionDataResource::AUTHORITY_CANCEL);
    }

    /**
     * Set order payment capture authority.
     *
     * @param int $authorityCode
     * @return void
     */
    public function setCaptureAuthority(int $authorityCode): void
    {
        $this->setData(OrderExtensionDataResource::AUTHORITY_CAPTURE, $authorityCode);
    }

    /**
     * Set order payment refund authority.
     *
     * @param int $authorityCode
     * @return void
     */
    public function setRefundAuthority(int $authorityCode): void
    {
        $this->setData(OrderExtensionDataResource::AUTHORITY_REFUND, $authorityCode);
    }

    /**
     * Set order cancel authority.
     *
     * @param int $authorityCode
     * @return void
     */
    public function setCancelAuthority(int $authorityCode): void
    {
        $this->setData(OrderExtensionDataResource::AUTHORITY_CANCEL, $authorityCode);
    }
}
