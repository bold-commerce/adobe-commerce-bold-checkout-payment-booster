<?php
use Magento\Framework\App\ObjectManager;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;

/** @var SourceItemInterfaceFactory $sourceItemFactory */
$sourceItemFactory = ObjectManager::getInstance()->get(SourceItemInterfaceFactory::class);

/** @var SourceItemsSaveInterface $sourceItemsSave */
$sourceItemsSave = ObjectManager::getInstance()->get(SourceItemsSaveInterface::class);

$sku = 'simple'; // Or whatever your product SKU is

$sourceItem = $sourceItemFactory->create();
$sourceItem->setSourceCode('default');
$sourceItem->setSku($sku);
$sourceItem->setQuantity(100);
$sourceItem->setStatus(1); // 1 = In Stock

$sourceItemsSave->execute([$sourceItem]);
