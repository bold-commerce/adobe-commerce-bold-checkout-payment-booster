<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Order\UpdatePayments;

use Bold\CheckoutPaymentBooster\Model\Config;
use Bold\CheckoutPaymentBooster\Model\OrderExtensionDataRepository;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Model\Order;

/**
 * Cancel order service.
 */
class CancelOrder
{
    const ACTION = 'Canceled';

    /**
     * @var Config
     */
    private $config;

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
     * @var ChangeOrderStatus
     */
    private $changeOrderStatus;

    /**
     * @param OrderManagementInterface $orderManagement
     * @param OrderExtensionDataRepository $orderExtensionDataRepository
     * @param TransactionComment $transactionComment
     * @param Config $config
     * @param ChangeOrderStatus $changeOrderStatus
     */
    public function __construct(
        OrderManagementInterface $orderManagement,
        OrderExtensionDataRepository $orderExtensionDataRepository,
        TransactionComment $transactionComment,
        Config $config,
        ChangeOrderStatus $changeOrderStatus
    ) {
        $this->config = $config;
        $this->orderManagement = $orderManagement;
        $this->orderExtensionDataRepository = $orderExtensionDataRepository;
        $this->transactionComment = $transactionComment;
        $this->changeOrderStatus = $changeOrderStatus;
    }

    /**
     * Cancel order or change order status depending on configuration.
     *
     * @param OrderInterface&Order $order
     * @return void
     * @throws AlreadyExistsException
     * @throws \Exception
     */
    public function execute(OrderInterface $order): void
    {
        $websiteId = (int) $order->getStore()->getWebsiteId();

        // regular flow and cancel the order
        if (!$this->config->isDelayedCaptureEnabled($websiteId)) {
            $this->cancelOrder($order);
        }

        // merchant wants to cancel the order even if delayed capture is enabled and failed capture
        if ($this->config->isDelayedCaptureEnabled($websiteId)
            && $this->config->isDelayedCaptureCancelOrder($websiteId)
        ) {
            $this->cancelOrder($order);
        }

        // merchant wants to change order status if delayed capture and order is not captured
        if ($this->config->isDelayedCaptureEnabled($websiteId) &&
            $this->config->isDelayedCaptureChangeOrderStatus($websiteId)) {
            $this->changeOrderStatus->execute($order, $this->config->isDelayedCaptureNewOrderStatus($websiteId));
        }
    }

    /**
     * Cancels the specified order.
     *
     * @param OrderInterface $order The order to be cancelled.
     * @return void
     * @throws \Exception If an error occurs during the cancellation process.
     */
    private function cancelOrder(OrderInterface $order)
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
