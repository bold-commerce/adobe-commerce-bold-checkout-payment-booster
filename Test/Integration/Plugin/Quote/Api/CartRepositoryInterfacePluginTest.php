<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\Plugin\Quote\Api;

use Bold\CheckoutPaymentBooster\Api\MagentoQuoteBoldOrderRepositoryInterface;
use Bold\CheckoutPaymentBooster\Model\MagentoQuoteBoldOrder;
use Bold\CheckoutPaymentBooster\Model\ResourceModel\MagentoQuoteBoldOrder as MagentoQuoteBoldOrderResourceModel;
use Bold\CheckoutPaymentBooster\Plugin\Quote\Api\CartRepositoryInterfacePlugin;
use Bold\CheckoutPaymentBooster\Test\Integration\_Assertions\AssertPluginIsConfiguredCorrectly;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ResourceModel\Quote as CartResourceModel;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class CartRepositoryInterfacePluginTest extends TestCase
{
    use AssertPluginIsConfiguredCorrectly;

    private const PLUGIN_NAME = 'bold_booster_add_public_order_id';

    /**
     * @magentoAppArea frontend
     */
    public function testIsConfiguredCorrectlyInFrontend(): void
    {
        self::assertPluginIsConfiguredCorrectly(
            self::PLUGIN_NAME,
            CartRepositoryInterfacePlugin::class,
            CartRepositoryInterface::class
        );
    }

    /**
     * @magentoAppArea webapi_rest
     */
    public function testIsConfiguredCorrectlyInRestApi(): void
    {
        self::assertPluginIsConfiguredCorrectly(
            self::PLUGIN_NAME,
            CartRepositoryInterfacePlugin::class,
            CartRepositoryInterface::class
        );
    }

    /**
     * @magentoAppArea frontend
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/magento_quote_bold_order.php
     * @throws NoSuchEntityException
     */
    public function testRetrievesBoldOrderIdAfterCartRetrievalSuccessfully(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var MagentoQuoteBoldOrder $magentoQuoteBoldOrder */
        $magentoQuoteBoldOrder = $objectManager->create(MagentoQuoteBoldOrder::class);
        /** @var MagentoQuoteBoldOrderResourceModel $magentoQuoteBoldOrderResourceModel */
        $magentoQuoteBoldOrderResourceModel = $objectManager->create(MagentoQuoteBoldOrderResourceModel::class);
        /** @var CartRepositoryInterface $cartRepository */
        $cartRepository = $objectManager->create(CartRepositoryInterface::class);

        $magentoQuoteBoldOrderResourceModel->load(
            $magentoQuoteBoldOrder,
            'e5537d5a79264a53995b9ccf6b86225b46925006f6e24a59a8892fbb524b1aa0',
            'bold_order_id'
        );

        $cart = $cartRepository->get((int)$magentoQuoteBoldOrder->getQuoteId());

        self::assertSame(
            'e5537d5a79264a53995b9ccf6b86225b46925006f6e24a59a8892fbb524b1aa0',
            $cart->getExtensionAttributes()->getBoldOrderId()
        );
    }

    /**
     * @magentoAppArea frontend
     * @magentoDataFixture Magento/Checkout/_files/quote_with_items_saved.php
     * @throws NoSuchEntityException
     */
    public function testSavesBoldOrderIdAfterCartIsSaved(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var CartInterface&Quote $cart */
        $cart = $objectManager->create(CartInterface::class);
        /** @var CartResourceModel $cartResourceModel */
        $cartResourceModel = $objectManager->create(CartResourceModel::class);
        /** @var CartRepositoryInterface $cartRepository */
        $cartRepository = $objectManager->create(CartRepositoryInterface::class);
        /** @var MagentoQuoteBoldOrderRepositoryInterface $magentoQuoteBoldOrderRepository */
        $magentoQuoteBoldOrderRepository = $objectManager->create(MagentoQuoteBoldOrderRepositoryInterface::class);

        $cartResourceModel->load($cart, 'test_order_item_with_items', 'reserved_order_id');

        $cart
            ->getExtensionAttributes()
            ->setBoldOrderId('ad4b1b411a7a45538bfcb48cadb9af0e9fc92360c2574ca29b0099377007d6bc');

        $cartRepository->save($cart);

        /** @var int|string $cartId */
        $cartId = $cart->getId();
        $magentoQuoteBoldOrder = $magentoQuoteBoldOrderRepository->getByQuoteId((int)$cartId);

        self::assertSame(
            'ad4b1b411a7a45538bfcb48cadb9af0e9fc92360c2574ca29b0099377007d6bc',
            $magentoQuoteBoldOrder->getBoldOrderId()
        );
    }
}
