<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Order\UpdatePayments;

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
     * @param CreditmemoFactory $creditMemoFactory
     * @param CreditmemoService $creditMemoService
     */
    public function __construct(
        CreditmemoFactory $creditMemoFactory,
        CreditmemoService $creditMemoService
    ) {
        $this->creditMemoFactory = $creditMemoFactory;
        $this->creditMemoService = $creditMemoService;
    }

    /**
     * Create credit memo.
     * TODO: test and update after the refund request from Bold is implemented.
     *
     * @param OrderInterface $order
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute(OrderInterface $order): void
    {
        $creditMemo = $this->creditMemoFactory->createByOrder($order, $order->getData());
        $this->creditMemoService->refund($creditMemo);
    }
}
