<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Order;

use Bold\CheckoutPaymentBooster\Model\OrderExtensionDataRepository;
use Magento\Framework\Exception\LocalizedException;

/**
 * Retrieve Bold order public id by Magento order entity id.
 */
class GetOrderPublicIdByOrderId
{
    /**
     * @var OrderExtensionDataRepository
     */
    private $orderExtensionDataRepository;

    /**
     * @param OrderExtensionDataRepository $orderExtensionDataRepository
     */
    public function __construct(
        OrderExtensionDataRepository $orderExtensionDataRepository
    ) {
        $this->orderExtensionDataRepository = $orderExtensionDataRepository;
    }

    /**
     * Retrieve Bold order public id by Magento order entity id.
     *
     * @param int $orderId
     * @return string
     * @throws LocalizedException
     */
    public function execute(int $orderId): string
    {
        $orderExtensionData = $this->orderExtensionDataRepository->getByOrderId($orderId);
        if (!$orderExtensionData) {
            throw new LocalizedException(__('Order public id is not set.'));
        }

        return $orderExtensionData->getPublicId();
    }
}
