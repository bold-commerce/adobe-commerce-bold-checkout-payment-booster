<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Order\UpdatePayments;

use Bold\CheckoutPaymentBooster\Model\OrderExtensionDataRepository;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderManagementInterface;

/**
 * Cancel order service.
 */
class CancelOrder
{
    /**
     * @var OrderManagementInterface
     */
    private $orderManagement;

    /**
     * @var OrderExtensionDataRepository
     */
    private $orderExtensionDataRepository;

    /**
     * @param OrderManagementInterface $orderManagement
     */
    public function __construct(
        OrderManagementInterface $orderManagement,
        OrderExtensionDataRepository $orderExtensionDataRepository
    ) {
        $this->orderManagement = $orderManagement;
        $this->orderExtensionDataRepository = $orderExtensionDataRepository;
    }

    /**
     * Cancel order.
     *
     * @param OrderInterface $order
     * @return void
     */
    public function execute(OrderInterface $order): void
    {
        $orderExtensionData = $this->orderExtensionDataRepository->getByOrderId((int)$order->getId());
        $orderExtensionData->setIsCancelInProgress(true);
        $this->orderExtensionDataRepository->save($orderExtensionData);
        try {
            $this->orderManagement->cancel((int)$order->getEntityId());
        } catch (\Exception $e) {
            $orderExtensionData->setIsCancelInProgress(false);
            $this->orderExtensionDataRepository->save($orderExtensionData);
            throw $e;
        }
        $orderExtensionData->setIsCancelInProgress(false);
        $this->orderExtensionDataRepository->save($orderExtensionData);
    }
}
