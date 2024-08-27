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
    )
    {
        $this->creditMemoFactory = $creditMemoFactory;
        $this->creditMemoService = $creditMemoService;
    }

    public function execute(OrderInterface $order): void
    {
        // TODO: implement in INTER-4601.
    }
}
