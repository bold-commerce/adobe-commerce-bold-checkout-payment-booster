<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Order\UpdatePayments;

use Bold\CheckoutPaymentBooster\Model\OrderExtensionDataRepository;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

/**
 * Cancel order service.
 */
class CancelOrder
{
    const ACTION = 'Canceled';

    /**
     * @var OrderManagementInterface
     */
    private $orderManagement;

    /**
     * @var OrderExtensionDataRepository
     */
    private $orderExtensionDataRepository;

    /**
     * @var TransactionComment
     */
    private $transactionComment;

    /**
     * @param OrderManagementInterface $orderManagement
     * @param OrderExtensionDataRepository $orderExtensionDataRepository
     * @param TransactionComment $transactionComment
     */
    public function __construct(
        OrderManagementInterface $orderManagement,
        OrderExtensionDataRepository $orderExtensionDataRepository,
        TransactionComment $transactionComment
    ) {
        $this->orderManagement = $orderManagement;
        $this->orderExtensionDataRepository = $orderExtensionDataRepository;
        $this->transactionComment = $transactionComment;
    }

    /**
     * Cancel order.
     *
     * @param OrderInterface&Order $order
     * @return void
     * @throws AlreadyExistsException
     */
    public function execute(OrderInterface $order): void
    {
        $orderExtensionData = $this->orderExtensionDataRepository->getByOrderId((int)$order->getId());
        $orderExtensionData->setIsCancelInProgress(true);
        $this->orderExtensionDataRepository->save($orderExtensionData);
        try {
            $this->orderManagement->cancel((int)$order->getEntityId());
            $this->transactionComment->addComment(self::ACTION, $order);
        } catch (\Exception $e) {
            $orderExtensionData->setIsCancelInProgress(false);
            $this->orderExtensionDataRepository->save($orderExtensionData);
            throw $e;
        }
        $orderExtensionData->setIsCancelInProgress(false);
        $this->orderExtensionDataRepository->save($orderExtensionData);
    }
}
