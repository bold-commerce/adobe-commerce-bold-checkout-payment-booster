<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\Plugin\Booster\Service\DigitalWallets\MagentoQuote;

use Bold\CheckoutPaymentBooster\Test\Integration\_Support\IntegrationTestCase;
use Bold\CheckoutPaymentBooster\Test\Integration\_Support\NonBaseCurrencyQuoteTrait;
use Bold\CheckoutPaymentBooster\Model\CheckoutData;
use Bold\CheckoutPaymentBooster\Model\Order\OrderExtensionData;
use Bold\CheckoutPaymentBooster\Model\Order\OrderExtensionDataFactory;
use Bold\CheckoutPaymentBooster\Model\OrderExtensionDataRepository;
use Bold\CheckoutPaymentBooster\Plugin\Booster\Service\DigitalWallets\MagentoQuote\CreatorPlugin;
use Bold\CheckoutPaymentBooster\Service\DigitalWallets\MagentoQuote\Creator;
use Bold\CheckoutPaymentBooster\Test\Integration\_Assertions\AssertPluginIsConfiguredCorrectly;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Quote\Api\Data\CartExtensionInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order as OrderResource;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * @magentoAppIsolation enabled
 */
class CreatorPluginTest extends IntegrationTestCase
{
    use AssertPluginIsConfiguredCorrectly;
    use NonBaseCurrencyQuoteTrait;

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
     * @dataProvider nonBaseDisplayCurrencyProvider
     * @magentoDbIsolation enabled
     * @magentoAppArea frontend
     * @magentoDataFixture Magento/Catalog/_files/product_virtual.php
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/magento_quote_bold_order.php
     */
    public function testReinitializesBoldOrderDataWithNonBaseCurrencyQuote(string $displayCurrency): void
    {
        $boldCheckoutDataStub = $this->createStub(CheckoutData::class);
        $objectManager = Bootstrap::getObjectManager();
        $storeManager = $objectManager->get(StoreManagerInterface::class);
        $productRepository = $objectManager->create(ProductRepositoryInterface::class);
        $product = $productRepository->get('virtual-product');
        $productRequestData = [
            'bold_order_id' => 'e5537d5a79264a53995b9ccf6b86225b46925006f6e24a59a8892fbb524b1aa0',
            'qty' => 1,
        ];
        $magentoQuoteCreator = $objectManager->create(Creator::class);

        $boldCheckoutDataStub->method('resetCheckoutData')->willReturn(null);
        $boldCheckoutDataStub->method('initCheckoutData')->willReturn(null);
        $boldCheckoutDataStub->method('getPublicOrderId')
            ->willReturn('aca5efca525f4748be5820d62d95c88b2e9b11b98bb643fc93b2109500a2f993');

        $objectManager->configure([CheckoutData::class => ['shared' => true]]);
        $objectManager->addSharedInstance($boldCheckoutDataStub, CheckoutData::class);

        $this->setStoreDisplayCurrency($displayCurrency, (int) $storeManager->getStore()->getId());

        $result = $magentoQuoteCreator->createQuote($storeManager->getStore()->getId(), $product, $productRequestData);
        $quote = $this->applyDisplayCurrencyToQuote($result['quote'], $displayCurrency);
        $this->assertQuoteUsesNonBaseCurrency($quote, $displayCurrency);

        self::assertSame(
            'aca5efca525f4748be5820d62d95c88b2e9b11b98bb643fc93b2109500a2f993',
            $result['quote']->getExtensionAttributes()->getBoldOrderId()
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

    /**
     * After order_complete clears bold_order_id, a stale client ID must still trigger re-init.
     *
     * order.php runs default_rollback (deletes catalog); reload virtual product after it.
     *
     * @magentoAppArea frontend
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/magento_quote_bold_order.php
     * @magentoDataFixture Magento/Sales/_files/order.php
     * @magentoDataFixture Magento/Catalog/_files/product_virtual.php
     */
    public function testCreatorPluginRejectsClearedButCompletedPublicOrderId(): void
    {
        $completedPublicOrderId = 'e5537d5a79264a53995b9ccf6b86225b46925006f6e24a59a8892fbb524b1aa0';
        $freshPublicOrderId = 'aca5efca525f4748be5820d62d95c88b2e9b11b98bb643fc93b2109500a2f993';

        $objectManager = Bootstrap::getObjectManager();
        /** @var OrderExtensionDataFactory $orderExtensionDataFactory */
        $orderExtensionDataFactory = $objectManager->get(OrderExtensionDataFactory::class);
        /** @var OrderExtensionDataRepository $orderExtensionDataRepository */
        $orderExtensionDataRepository = $objectManager->get(OrderExtensionDataRepository::class);
        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        /** @var OrderResource $orderResource */
        $orderResource = $objectManager->create(OrderResource::class);

        $orderResource->load($order, '100000001', 'increment_id');

        /** @var OrderExtensionData $orderExtensionData */
        $orderExtensionData = $orderExtensionDataFactory->create();
        $orderExtensionData->setOrderId((int)$order->getEntityId());
        $orderExtensionData->setPublicId($completedPublicOrderId);
        $orderExtensionDataRepository->save($orderExtensionData);

        $boldCheckoutDataStub = $this->createMock(CheckoutData::class);
        $boldCheckoutDataStub->expects(self::once())->method('resetCheckoutData');
        $boldCheckoutDataStub->expects(self::once())->method('initCheckoutData');
        $boldCheckoutDataStub->method('getPublicOrderId')->willReturn($freshPublicOrderId);

        $objectManager->configure([CheckoutData::class => ['shared' => true]]);
        $objectManager->addSharedInstance($boldCheckoutDataStub, CheckoutData::class);

        /** @var StoreManagerInterface $storeManager */
        $storeManager = $objectManager->get(StoreManagerInterface::class);
        /** @var ProductRepositoryInterface $productRepository */
        $productRepository = $objectManager->create(ProductRepositoryInterface::class);
        $product = $productRepository->get('virtual-product');
        /** @var Creator $magentoQuoteCreator */
        $magentoQuoteCreator = $objectManager->create(Creator::class);

        $result = $magentoQuoteCreator->createQuote(
            $storeManager->getStore()->getId(),
            $product,
            [
                'bold_order_id' => $completedPublicOrderId,
                'qty' => 1,
            ]
        );

        /** @var CartExtensionInterface $cartExtension */
        $cartExtension = $result['quote']->getExtensionAttributes();

        self::assertSame($freshPublicOrderId, $cartExtension->getBoldOrderId());
    }
}
