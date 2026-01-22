<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Integration\InventoryChecker;

use Bold\CheckoutPaymentBooster\Api\Data\Integration\InventoryItemResultInterfaceFactory;
use Magento\Framework\App\ObjectManager;
use Psr\Log\LoggerInterface;

/**
 * MSI (Multi-Source Inventory) implementation.
 *
 * This class contains direct references to MSI classes which may not exist in all Magento installations.
 * The InventoryCheckerFactory ensures this code only runs when MSI modules are enabled at runtime.
 * PHPStan errors for missing MSI classes are expected in CI environments testing backward compatibility.
 */
class MsiInventoryChecker implements InventoryCheckerInterface
{
    /**
     * @var InventoryItemResultInterfaceFactory
     */
    private $itemResultFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * MSI services (injected via Object Manager for optional dependency)
     *
     * @var mixed
     */
    private $stockResolver;

    /**
     * @var mixed
     */
    private $areProductsSalableForRequestedQty;

    /**
     * @var mixed
     */
    private $requestFactory;

    /**
     * @var mixed
     */
    private $getProductSalableQty;

    /**
     * @param InventoryItemResultInterfaceFactory $itemResultFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        InventoryItemResultInterfaceFactory $itemResultFactory,
        LoggerInterface $logger
    ) {
        $this->itemResultFactory = $itemResultFactory;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function checkInventory(array $productItems, $context): array
    {
        /** @var \Magento\Store\Api\Data\WebsiteInterface $website */
        $website = $context;

        // Lazy-load MSI services (to avoid DI errors when MSI is disabled)
        if ($this->stockResolver === null) {
            $objectManager = ObjectManager::getInstance();
            $this->stockResolver = $objectManager->get(
                \Magento\InventorySalesApi\Api\StockResolverInterface::class
            );
            $this->areProductsSalableForRequestedQty = $objectManager->get(
                \Magento\InventorySalesApi\Api\AreProductsSalableForRequestedQtyInterface::class
            );
            $this->requestFactory = $objectManager->get(
                \Magento\InventorySalesApi\Api\Data\IsProductSalableForRequestedQtyRequestInterfaceFactory::class
            );
            $this->getProductSalableQty = $objectManager->get(
                \Magento\InventorySalesApi\Api\GetProductSalableQtyInterface::class
            );
        }

        // Resolve stock ID
        $stock = $this->stockResolver->execute(
            \Magento\InventorySalesApi\Api\Data\SalesChannelInterface::TYPE_WEBSITE,
            $website->getCode()
        );
        $stockId = (int) $stock->getStockId();

        // Build MSI requests
        $inventoryRequests = [];
        foreach ($productItems as $item) {
            $product = $item['product'];
            $inventoryRequests[] = $this->requestFactory->create([
                'sku' => $product->getSku(),
                'qty' => $item['quantity'],
            ]);
        }

        // Check salability
        $salabilityResults = $this->areProductsSalableForRequestedQty->execute(
            $inventoryRequests,
            $stockId
        );

        // Build results - order is preserved by index
        $results = [];
        foreach ($salabilityResults as $index => $salabilityResult) {
            $product = $productItems[$index]['product'];
            $requestedQty = $productItems[$index]['quantity'];
            $sku = $product->getSku();
            $isSalable = $salabilityResult->isSalable();

            // Get available quantity
            try {
                $availableQty = $this->getProductSalableQty->execute($sku, $stockId);
            } catch (\Exception $e) {
                $this->logger->error(
                    "Failed to get salable qty for SKU {$sku}: " . $e->getMessage()
                );
                $availableQty = 0.0;
            }

            // Extract error message
            $reason = null;
            if (!$isSalable) {
                $errors = $salabilityResult->getErrors();
                $reason = !empty($errors) ? $errors[0]->getMessage() : 'Insufficient inventory';
            }

            // Create result
            $itemResult = $this->itemResultFactory->create();
            $itemResult->setSku($sku);
            $itemResult->setProductId((string) $product->getId());
            $itemResult->setRequestedQuantity($requestedQty);
            $itemResult->setAvailableQuantity($availableQty);
            $itemResult->setIsAvailable($isSalable);
            $itemResult->setReason($reason);

            $results[] = $itemResult;
        }

        return $results;
    }
}
