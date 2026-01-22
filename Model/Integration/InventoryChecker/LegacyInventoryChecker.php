<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Integration\InventoryChecker;

use Bold\CheckoutPaymentBooster\Api\Data\Integration\InventoryItemResultInterfaceFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Psr\Log\LoggerInterface;

/**
 * Legacy CatalogInventory implementation.
 */
class LegacyInventoryChecker implements InventoryCheckerInterface
{
    /**
     * @var InventoryItemResultInterfaceFactory
     */
    private $itemResultFactory;

    /**
     * @var StockRegistryInterface
     */
    private $stockRegistry;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param InventoryItemResultInterfaceFactory $itemResultFactory
     * @param StockRegistryInterface $stockRegistry
     * @param LoggerInterface $logger
     */
    public function __construct(
        InventoryItemResultInterfaceFactory $itemResultFactory,
        StockRegistryInterface $stockRegistry,
        LoggerInterface $logger
    ) {
        $this->itemResultFactory = $itemResultFactory;
        $this->stockRegistry = $stockRegistry;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function checkInventory(array $productItems, $context): array
    {
        $websiteId = (int) $context;
        $results = [];

        foreach ($productItems as $item) {
            $product = $item['product'];
            $requestedQty = $item['quantity'];
            $sku = $product->getSku();

            try {
                // Get stock item
                $stockItem = $this->stockRegistry->getStockItemBySku($sku, $websiteId);

                $isAvailable = false;
                $availableQty = 0.0;
                $reason = null;

                if (!$stockItem->getManageStock()) {
                    // Stock management disabled - always available
                    $isAvailable = true;
                    $availableQty = PHP_FLOAT_MAX;
                } elseif (!$stockItem->getIsInStock()) {
                    // Out of stock
                    $isAvailable = false;
                    $availableQty = 0.0;
                    $reason = 'Product is out of stock';
                } else {
                    // Calculate salable quantity
                    $physicalQty = $stockItem->getQty();
                    $minQty = $stockItem->getMinQty();
                    $availableQty = max(0, $physicalQty - $minQty);

                    // Check if requested quantity is available
                    if ($availableQty >= $requestedQty) {
                        $isAvailable = true;
                    } else {
                        // Check backorders
                        if ($stockItem->getBackorders() > 0) {
                            // Backorders enabled
                            $isAvailable = true;
                            $reason = null;
                        } else {
                            $isAvailable = false;
                            $reason = 'The requested qty is not available';
                        }
                    }
                }

                // Create result
                $itemResult = $this->itemResultFactory->create();
                $itemResult->setSku($sku);
                $itemResult->setProductId((string) $product->getId());
                $itemResult->setRequestedQuantity($requestedQty);
                $itemResult->setAvailableQuantity($availableQty);
                $itemResult->setIsAvailable($isAvailable);
                $itemResult->setReason($reason);

                $results[] = $itemResult;
            } catch (\Exception $e) {
                $this->logger->error(
                    "Failed to check inventory for SKU {$sku}: " . $e->getMessage(),
                    ['product_id' => $product->getId()]
                );

                $itemResult = $this->itemResultFactory->create();
                $itemResult->setSku($sku);
                $itemResult->setProductId((string) $product->getId());
                $itemResult->setRequestedQuantity($requestedQty);
                $itemResult->setAvailableQuantity(0.0);
                $itemResult->setIsAvailable(false);
                $itemResult->setReason('Error checking inventory: ' . $e->getMessage());

                $results[] = $itemResult;
            }
        }

        return $results;
    }
}
