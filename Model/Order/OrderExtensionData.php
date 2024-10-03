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
     * Set is order capture currently in progress.
     *
     * @return void
     */
    public function setIsCaptureInProgress(bool $inProgress)
    {
        $this->setData(OrderExtensionDataResource::IS_CAPTURE_IN_PROGRESS, (int)$inProgress);
    }

    /**
     * Get is order capture currently in progress.
     *
     * @return bool
     */
    public function getIsCaptureInProgress(): bool
    {
        return (bool)$this->getData(OrderExtensionDataResource::IS_CAPTURE_IN_PROGRESS);
    }

    /**
     * Get is order refund currently in progress.
     *
     * @return void
     */
    public function setIsRefundInProgress(bool $inProgress)
    {
        $this->setData(OrderExtensionDataResource::IS_REFUND_IN_PROGRESS, (int)$inProgress);
    }

    /**
     * Get is order refund currently in progress.
     *
     * @return bool
     */
    public function getIsRefundInProgress(): bool
    {
        return (bool)$this->getData(OrderExtensionDataResource::IS_REFUND_IN_PROGRESS);
    }

    /**
     * Get is order cancel currently in progress.
     *
     * @return void
     */
    public function setIsCancelInProgress(bool $inProgress)
    {
        $this->setData(OrderExtensionDataResource::IS_CANCEL_IN_PROGRESS, (int)$inProgress);
    }

    /**
     * Get is order cancel currently in progress.
     *
     * @return bool
     */
    public function getIsCancelInProgress(): bool
    {
        return (bool)$this->getData(OrderExtensionDataResource::IS_CANCEL_IN_PROGRESS);
    }
}
