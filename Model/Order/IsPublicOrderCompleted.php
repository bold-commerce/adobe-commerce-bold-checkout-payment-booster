<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Order;

use Bold\CheckoutPaymentBooster\Api\Data\MagentoQuoteBoldOrderInterface;
use Bold\CheckoutPaymentBooster\Api\Data\MagentoQuoteBoldOrderInterfaceFactory;
use Bold\CheckoutPaymentBooster\Model\MagentoQuoteBoldOrder;
use Bold\CheckoutPaymentBooster\Model\OrderExtensionDataRepository;
use Bold\CheckoutPaymentBooster\Model\ResourceModel\MagentoQuoteBoldOrder as ResourceModel;

/**
 * Determines whether a Bold public_order_id was already consumed by a completed Magento order.
 */
class IsPublicOrderCompleted
{
    /**
     * @var OrderExtensionDataRepository
     */
    private $orderExtensionDataRepository;

    /**
     * @var MagentoQuoteBoldOrderInterfaceFactory
     */
    private $magentoQuoteBoldOrderFactory;

    /**
     * @var ResourceModel
     */
    private $resourceModel;

    /**
     * @param OrderExtensionDataRepository $orderExtensionDataRepository
     * @param MagentoQuoteBoldOrderInterfaceFactory $magentoQuoteBoldOrderFactory
     * @param ResourceModel $resourceModel
     */
    public function __construct(
        OrderExtensionDataRepository $orderExtensionDataRepository,
        MagentoQuoteBoldOrderInterfaceFactory $magentoQuoteBoldOrderFactory,
        ResourceModel $resourceModel
    ) {
        $this->orderExtensionDataRepository = $orderExtensionDataRepository;
        $this->magentoQuoteBoldOrderFactory = $magentoQuoteBoldOrderFactory;
        $this->resourceModel = $resourceModel;
    }

    /**
     * @param string $publicOrderId
     * @return bool
     */
    public function execute(string $publicOrderId): bool
    {
        if ($publicOrderId === '') {
            return false;
        }

        $orderExtensionData = $this->orderExtensionDataRepository->getByPublicOrderId($publicOrderId);
        if ($orderExtensionData->getId() && $orderExtensionData->getOrderId()) {
            return true;
        }

        /** @var MagentoQuoteBoldOrderInterface&MagentoQuoteBoldOrder $magentoQuoteBoldOrder */
        $magentoQuoteBoldOrder = $this->magentoQuoteBoldOrderFactory->create();
        $this->resourceModel->load($magentoQuoteBoldOrder, $publicOrderId, 'bold_order_id');

        if ($magentoQuoteBoldOrder->getId() === null) {
            return false;
        }

        return $magentoQuoteBoldOrder->isProcessed();
    }
}
