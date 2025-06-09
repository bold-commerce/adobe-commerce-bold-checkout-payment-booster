<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\Plugin\Booster\Service\DigitalWallets\MagentoQuote;

use Bold\CheckoutPaymentBooster\Model\CheckoutData;
use Bold\CheckoutPaymentBooster\Plugin\Booster\Service\DigitalWallets\MagentoQuote\CreatorPlugin;
use Bold\CheckoutPaymentBooster\Service\DigitalWallets\MagentoQuote\Creator;
use Bold\CheckoutPaymentBooster\Test\Integration\_Assertions\AssertPluginIsConfiguredCorrectly;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Quote\Api\Data\CartExtensionInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoAppIsolation enabled
 */
class CreatorPluginTest extends TestCase
{
    use AssertPluginIsConfiguredCorrectly;

    private const PLUGIN_NAME = 'bold_booster_reinit_order_data';

    /**
     * @magentoAppArea frontend
     */
    public function testIsConfiguredCorrectly(): void
    {
        self::assertPluginIsConfiguredCorrectly(self::PLUGIN_NAME, CreatorPlugin::class, Creator::class);
    }

    /**
     * @magentoAppArea frontend
     * @magentoDataFixture Magento/Catalog/_files/product_virtual.php
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/magento_quote_bold_order.php
     */
    public function testReinitializesBoldOrderDataSuccessfully(): void
    {
        $boldCheckoutDataStub = $this->createStub(CheckoutData::class);
        $objectManager = Bootstrap::getObjectManager();
        /** @var StoreManagerInterface $storeManager */
        $storeManager = $objectManager->get(StoreManagerInterface::class);
        /** @var ProductRepositoryInterface $productRepository */
        $productRepository = $objectManager->create(ProductRepositoryInterface::class);
        $product = $productRepository->get('virtual-product');
        $productRequestData = [
            'bold_order_id' => 'e5537d5a79264a53995b9ccf6b86225b46925006f6e24a59a8892fbb524b1aa0',
            'qty' => 1,
        ];
        /** @var Creator $magentoQuoteCreator */
        $magentoQuoteCreator = $objectManager->create(Creator::class);

        $boldCheckoutDataStub
            ->method('resetCheckoutData')
            ->willReturn(null);
        $boldCheckoutDataStub
            ->method('initCheckoutData')
            ->willReturn(null);
        $boldCheckoutDataStub
            ->method('getPublicOrderId')
            ->willReturn('aca5efca525f4748be5820d62d95c88b2e9b11b98bb643fc93b2109500a2f993');

        $objectManager->configure(
            [
                CheckoutData::class => [
                    'shared' => true,
                ],
            ]
        );
        $objectManager->addSharedInstance($boldCheckoutDataStub, CheckoutData::class);

        $result = $magentoQuoteCreator->createQuote($storeManager->getStore()->getId(), $product, $productRequestData);
        /** @var CartExtensionInterface $cartExtension */
        $cartExtension = $result['quote']->getExtensionAttributes();

        self::assertSame(
            'aca5efca525f4748be5820d62d95c88b2e9b11b98bb643fc93b2109500a2f993',
            $cartExtension->getBoldOrderId()
        );
    }

    /**
     * @dataProvider doesNotReinitializeBoldOrderDataSuccessfullyDataProvider
     * @magentoAppArea frontend
     * @magentoDataFixture Magento/Catalog/_files/product_virtual.php
     */
    public function testDoesNotReinitializeBoldOrderDataSuccessfully(?string $boldOrderId): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var StoreManagerInterface $storeManager */
        $storeManager = $objectManager->get(StoreManagerInterface::class);
        /** @var ProductRepositoryInterface $productRepository */
        $productRepository = $objectManager->create(ProductRepositoryInterface::class);
        $product = $productRepository->get('virtual-product');
        $productRequestData = [
            'qty' => 1,
        ];
        /** @var Creator $magentoQuoteCreator */
        $magentoQuoteCreator = $objectManager->create(Creator::class);

        if ($boldOrderId !== null) {
            $productRequestData['bold_order_id'] = $boldOrderId;
        }

        $result = $magentoQuoteCreator->createQuote($storeManager->getStore()->getId(), $product, $productRequestData);
        /** @var CartExtensionInterface $cartExtension */
        $cartExtension = $result['quote']->getExtensionAttributes();

        self::assertSame($boldOrderId ?? '', $cartExtension->getBoldOrderId());
    }

    /**
     * @return array<string, array<string, string|null>>
     */
    public function doesNotReinitializeBoldOrderDataSuccessfullyDataProvider(): array
    {
        return [
            'no bold order id' => [
                'boldOrderId' => null
            ],
            'non-existent bold order id' => [
                'boldOrderId' => '7d154b3a9c7e41c0978749c9599221b99f4eeb4d6cf040a4a17e99285f6fe6f5'
            ],
        ];
    }
}
