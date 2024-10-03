<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Order\UpdatePayments;

use Bold\CheckoutPaymentBooster\Model\OrderExtensionDataRepository;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Model\Service\CreditmemoService;

/**
 * Create credit memo for order service.
 */
class CreateCreditMemo
{
    /**
     * @var CreditmemoFactory
     */
    private $creditMemoFactory;

    /**
     * @var CreditmemoService
     */
    private $creditMemoService;

    /**
     * @var OrderExtensionDataRepository
     */
    private $orderExtensionDataRepository;

    /**
     * @param CreditmemoFactory $creditMemoFactory
     * @param CreditmemoService $creditMemoService
     * @param OrderExtensionDataRepository $orderExtensionDataRepository
     */
    public function __construct(
        CreditmemoFactory $creditMemoFactory,
        CreditmemoService $creditMemoService,
        OrderExtensionDataRepository $orderExtensionDataRepository
    ) {
        $this->creditMemoFactory = $creditMemoFactory;
        $this->creditMemoService = $creditMemoService;
        $this->orderExtensionDataRepository = $orderExtensionDataRepository;
    }

    /**
     * Create credit memo.
     *
     * @param OrderInterface $order
     * @return void
     * @throws LocalizedException
     */
    public function execute(OrderInterface $order): void
    {
        $orderExtensionData = $this->orderExtensionDataRepository->getByOrderId((int)$order->getId());
        $orderExtensionData->setIsRefundInProgress(true);
        $this->orderExtensionDataRepository->save($orderExtensionData);
        try {
            $creditMemo = $this->creditMemoFactory->createByOrder($order, $order->getData());
            $this->creditMemoService->refund($creditMemo);
        } catch (LocalizedException $e) {
            $orderExtensionData->setIsRefundInProgress(false);
            $this->orderExtensionDataRepository->save($orderExtensionData);
            throw $e;
        }
        $orderExtensionData->setIsRefundInProgress(false);
        $this->orderExtensionDataRepository->save($orderExtensionData);
    }
}
