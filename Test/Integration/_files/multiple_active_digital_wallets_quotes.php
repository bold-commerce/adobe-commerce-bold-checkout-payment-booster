<?php

declare(strict_types=1);

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\ResourceConnection;
use Magento\Quote\Model\Quote;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture('Magento/Catalog/_files/products.php');

$objectManager = Bootstrap::getObjectManager();
/** @var ProductRepositoryInterface $productRepository */
$productRepository = $objectManager->create(ProductRepositoryInterface::class);
/** @var ProductInterface&Product $product */
$product = $productRepository->get('simple');
$randomTimestamp = static function (): string {
    $minimumDate = strtotime('-3 months');
    $maximumDate = strtotime('-1 hour');

    return date('Y-m-d H:i:s', random_int($minimumDate, $maximumDate));
};
/** @var ResourceConnection $resourceConnection */
$resourceConnection = $objectManager->get(ResourceConnection::class);

for ($i = 0; $i < 5; $i++) {
    /** @var Quote $quote */
    $quote = $objectManager->create(Quote::class);

    $quote
        ->setStoreId(1)
        ->setIsActive(true)
        ->setIsMultiShipping(0)
        ->setCheckoutMethod('guest')
        ->setData('is_digital_wallets', true)
        ->addProduct($product, 2);
    $quote->save(); // @phpstan-ignore

    $resourceConnection
        ->getConnection()
        ->update(
            $resourceConnection->getTableName('quote'),
            [
                'updated_at' => $randomTimestamp(),
            ],
            [
                'entity_id = ?' => $quote->getId()
            ]
        );
}
