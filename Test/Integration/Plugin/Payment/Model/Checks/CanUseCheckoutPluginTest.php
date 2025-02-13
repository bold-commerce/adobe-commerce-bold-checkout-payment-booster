<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\Plugin\Payment\Model\Checks;

use Bold\CheckoutPaymentBooster\Model\MagentoQuoteBoldOrder;
use Bold\CheckoutPaymentBooster\Model\Payment\Gateway\Service as PaymentGatewayService;
use Bold\CheckoutPaymentBooster\Model\ResourceModel\MagentoQuoteBoldOrder as MagentoQuoteBoldOrderResourceModel;
use Bold\CheckoutPaymentBooster\Plugin\Payment\Model\Checks\CanUseCheckoutPlugin;
use Bold\CheckoutPaymentBooster\Test\Integration\_Assertions\AssertPluginIsConfiguredCorrectly;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Model\Checks\CanUseCheckout;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class CanUseCheckoutPluginTest extends TestCase
{
    use AssertPluginIsConfiguredCorrectly;

    private const PLUGIN_NAME = 'bold_booster_can_use_checkout_digital_wallets';

    /**
     * @magentoAppArea frontend
     */
    public function testIsConfiguredCorrectlyInFrontend(): void
    {
        self::assertPluginIsConfiguredCorrectly(self::PLUGIN_NAME, CanUseCheckoutPlugin::class, CanUseCheckout::class);
    }

    /**
     * @magentoAppArea webapi_rest
     */
    public function testIsConfiguredCorrectlyInRestApi(): void
    {
        self::assertPluginIsConfiguredCorrectly(self::PLUGIN_NAME, CanUseCheckoutPlugin::class, CanUseCheckout::class);
    }

    /**
     * @magentoAppArea frontend
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/magento_quote_bold_order.php
     * @throws NoSuchEntityException
     */
    public function testIsApplicableForBoldOrder(): void
    {
        $paymentMethodStub = $this->createStub(MethodInterface::class);
        $objectManager = Bootstrap::getObjectManager();
        /** @var MagentoQuoteBoldOrder $magentoQuoteBoldOrder */
        $magentoQuoteBoldOrder = $objectManager->create(MagentoQuoteBoldOrder::class);
        /** @var MagentoQuoteBoldOrderResourceModel $magentoQuoteBoldOrderResourceModel */
        $magentoQuoteBoldOrderResourceModel = $objectManager->create(MagentoQuoteBoldOrderResourceModel::class);
        /** @var CartRepositoryInterface $cartRepository */
        $cartRepository = $objectManager->create(CartRepositoryInterface::class);
        /** @var CanUseCheckout $canUseCheckout */
        $canUseCheckout = $objectManager->create(CanUseCheckout::class);

        $paymentMethodStub
            ->method('getCode')
            ->willReturn(PaymentGatewayService::CODE);

        $magentoQuoteBoldOrderResourceModel->load(
            $magentoQuoteBoldOrder,
            'e5537d5a79264a53995b9ccf6b86225b46925006f6e24a59a8892fbb524b1aa0',
            'bold_order_id'
        );

        /** @var CartInterface&Quote $quote */
        $quote = $cartRepository->get((int)$magentoQuoteBoldOrder->getQuoteId());

        $isApplicable = $canUseCheckout->isApplicable($paymentMethodStub, $quote);

        self::assertTrue($isApplicable);
    }

    /**
     * @magentoAppArea frontend
     */
    public function testIsNotApplicableForNonBoldOrder(): void
    {
        $paymentMethodStub = $this->createStub(MethodInterface::class);
        $objectManager = Bootstrap::getObjectManager();
        /** @var CartInterface&Quote $quote */
        $quote = $objectManager->create(CartInterface::class);
        /** @var CanUseCheckout $canUseCheckout */
        $canUseCheckout = $objectManager->create(CanUseCheckout::class);

        $paymentMethodStub
            ->method('getCode')
            ->willReturn('test');
        $paymentMethodStub
            ->method('canUseCheckout')
            ->willReturn(false);

        $isApplicable = $canUseCheckout->isApplicable($paymentMethodStub, $quote);

        self::assertFalse($isApplicable);
    }

    /**
     * @magentoAppArea frontend
     */
    public function testIsNotApplicableIfQuoteDoesNotHaveBoldOrderId(): void
    {
        $paymentMethodStub = $this->createStub(MethodInterface::class);
        $objectManager = Bootstrap::getObjectManager();
        /** @var CartInterface&Quote $quote */
        $quote = $objectManager->create(CartInterface::class);
        /** @var CanUseCheckout $canUseCheckout */
        $canUseCheckout = $objectManager->create(CanUseCheckout::class);

        $paymentMethodStub
            ->method('getCode')
            ->willReturn(PaymentGatewayService::CODE);
        $paymentMethodStub
            ->method('canUseCheckout')
            ->willReturn(false);

        $isApplicable = $canUseCheckout->isApplicable($paymentMethodStub, $quote);

        self::assertFalse($isApplicable);
    }
}
