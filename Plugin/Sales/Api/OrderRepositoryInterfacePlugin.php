<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Plugin\Sales\Api;

use Bold\CheckoutPaymentBooster\Model\OrderExtensionDataRepository;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * Plugin to load Bold extension attributes for orders.
 */
class OrderRepositoryInterfacePlugin
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
     * Load Bold extension attributes after getting order.
     *
     * @param OrderRepositoryInterface $subject
     * @param OrderInterface $result
     * @return OrderInterface
     */
    public function afterGet(
        OrderRepositoryInterface $subject,
        OrderInterface $result
    ): OrderInterface {
        $orderExtension = $result->getExtensionAttributes();
        
        if ($orderExtension === null) {
            return $result;
        }

        // Skip if already loaded
        if ($orderExtension->getIsBoldIntegrationCart() !== null) {
            return $result;
        }

        $orderId = (int)$result->getEntityId();
        if (!$orderId) {
            return $result;
        }

        $orderExtensionData = $this->orderExtensionDataRepository->getByOrderId($orderId);
        
        // Check if record exists (has ID)
        if (!$orderExtensionData->getId()) {
            return $result;
        }

        // Set is_bold_integration_cart attribute
        $orderExtension->setIsBoldIntegrationCart($orderExtensionData->getIsBoldIntegrationCart());

        return $result;
    }
}

