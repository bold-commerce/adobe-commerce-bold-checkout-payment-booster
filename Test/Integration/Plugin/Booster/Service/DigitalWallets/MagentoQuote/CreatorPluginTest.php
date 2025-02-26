<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\Plugin\Booster\Service\DigitalWallets\MagentoQuote;

use Bold\CheckoutPaymentBooster\Model\CheckoutData;
use Bold\CheckoutPaymentBooster\Plugin\Booster\Service\DigitalWallets\MagentoQuote\CreatorPlugin;
use Bold\CheckoutPaymentBooster\Service\DigitalWallets\MagentoQuote\Creator;
use Bold\CheckoutPaymentBooster\Test\Integration\_Assertions\AssertPluginIsConfiguredCorrectly;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

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
            'bold_order_id' => 'aca5efca525f4748be5820d62d95c88b',
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
            ->willReturn('2e9b11b98bb643fc93b2109500a2f993');

        $objectManager->configure(
            [
                CheckoutData::class => [
                    'shared' => true,
                ],
            ]
        );
        // @phpstan-ignore-next-line
        $objectManager->addSharedInstance($boldCheckoutDataStub, CheckoutData::class);

        $result = $magentoQuoteCreator->createQuote($storeManager->getStore()->getId(), $product, $productRequestData);

        /** @var CartRepositoryInterface $cartRepository */
        $cartRepository = $objectManager->create(CartRepositoryInterface::class);
        $quote = $cartRepository->get($result['quote']->getId());

        self::assertSame('2e9b11b98bb643fc93b2109500a2f993', $quote->getExtensionAttributes()->getBoldOrderId());
    }
}
