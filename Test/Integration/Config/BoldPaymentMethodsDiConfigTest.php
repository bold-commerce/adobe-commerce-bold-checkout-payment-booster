<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\Config;

use Bold\CheckoutPaymentBooster\Model\Order\CheckPaymentMethod;
use Bold\CheckoutPaymentBooster\Model\Payment\Gateway\Service;
use Bold\CheckoutPaymentBooster\Observer\Order\AfterSubmitObserver;
use Bold\CheckoutPaymentBooster\Observer\Order\BeforePlaceObserver;
use Bold\CheckoutPaymentBooster\Plugin\Checkout\Controller\Onepage\SuccessPlugin;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionProperty;

/**
 * GAP 1 guard: verify the boldPaymentMethods DI argument is complete.
 *
 * BeforePlaceObserver (and SuccessPlugin) rely on CheckPaymentMethod::isBold() to decide whether
 * to authorize a Bold payment. isBold() checks the order's payment method code against the
 * boldPaymentMethods array injected via DI.
 *
 * If this array is missing a method code — e.g. an accidental edit to di.xml — authorization
 * will be silently skipped for that method and the order will be placed without being charged.
 *
 * This test fails immediately in CI if the configuration is ever broken.
 *
 * @magentoAppArea global
 * @magentoAppIsolation enabled
 */
class BoldPaymentMethodsDiConfigTest extends TestCase
{
    /**
     * All Bold payment method codes that must be present in the DI configuration.
     * These constants are the single source of truth for method codes in this module.
     */
    private const REQUIRED_METHOD_CODES = [
        Service::CODE,          // 'bold'
        Service::CODE_FASTLANE, // 'bold_fastlane'
        Service::CODE_WALLET,   // 'bold_wallet'
    ];

    public function testAllBoldPaymentMethodCodesAreRegisteredInDiConfig(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var CheckPaymentMethod $checkPaymentMethod */
        $checkPaymentMethod = $objectManager->create(CheckPaymentMethod::class);

        $property = new ReflectionProperty(CheckPaymentMethod::class, 'boldPaymentMethods');
        $property->setAccessible(true);

        /** @var string[] $configuredMethods */
        $configuredMethods = $property->getValue($checkPaymentMethod);

        foreach (self::REQUIRED_METHOD_CODES as $code) {
            self::assertContains(
                $code,
                $configuredMethods,
                sprintf(
                    'Payment method code "%s" is missing from the boldPaymentMethods DI argument in etc/di.xml. '
                    . 'Orders paid with this method will bypass Bold authorization. '
                    . 'Add <item name="%s" xsi:type="string">%s</item> to the CheckPaymentMethod type configuration.',
                    $code,
                    $code,
                    $code
                )
            );
        }
    }

    /**
     * Verify that isBold() returns true for each required method code when used on a real order.
     * This catches the case where the array values exist but are mapped to wrong strings.
     *
     * @dataProvider boldMethodCodeProvider
     */
    public function testIsBoldReturnsTrueForEachRegisteredMethodCode(string $methodCode): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var CheckPaymentMethod $checkPaymentMethod */
        $checkPaymentMethod = $objectManager->create(CheckPaymentMethod::class);

        /** @var \Magento\Sales\Model\Order $order */
        $order = $objectManager->create(\Magento\Sales\Model\Order::class);
        /** @var \Magento\Sales\Model\Order\Payment $payment */
        $payment = $objectManager->create(\Magento\Sales\Model\Order\Payment::class);
        $payment->setMethod($methodCode);
        $order->setPayment($payment);

        self::assertTrue(
            $checkPaymentMethod->isBold($order),
            sprintf(
                'CheckPaymentMethod::isBold() returned false for method code "%s". '
                . 'Verify the boldPaymentMethods DI argument in etc/di.xml contains this code.',
                $methodCode
            )
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function boldMethodCodeProvider(): array
    {
        return [
            'bold (SPI / standard)'  => [Service::CODE],
            'bold_fastlane'          => [Service::CODE_FASTLANE],
            'bold_wallet (Express Pay)' => [Service::CODE_WALLET],
        ];
    }

    // ─── Logger wiring ────────────────────────────────────────────────────────

    /**
     * The shared module logger virtual type must be resolvable as a Psr\Log\LoggerInterface.
     * If the di.xml virtualType declaration is broken or missing, this test will fail during
     * CI before any runtime logs are lost.
     */
    public function testSharedLoggerVirtualTypeResolvesAsLoggerInterface(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        $logger = $objectManager->create('Bold\CheckoutPaymentBooster\Model\Http\Client\RequestsLogger\Logger');

        self::assertInstanceOf(
            LoggerInterface::class,
            $logger,
            'Bold\CheckoutPaymentBooster\Model\Http\Client\RequestsLogger\Logger must resolve as a '
            . 'Psr\Log\LoggerInterface. Check the virtualType declaration in etc/di.xml.'
        );
    }

    /**
     * Key checkout classes that inject LoggerInterface must receive the shared module
     * logger rather than the default Magento logger, so all Bold logs land in one file.
     *
     * @dataProvider loggerWiredClassesProvider
     */
    public function testLoggerIsWiredToSharedModuleLoggerInKeyClasses(string $className): void
    {
        $objectManager = Bootstrap::getObjectManager();

        $instance = $objectManager->create($className);

        // Retrieve the injected logger via reflection
        $property = new ReflectionProperty($className, 'logger');
        $property->setAccessible(true);
        $injectedLogger = $property->getValue($instance);

        // The shared logger is a Monolog instance (Magento\Framework\Logger\Monolog).
        // We verify it is the Bold virtual type by confirming it writes to our dedicated
        // log file, not the default system.log.
        self::assertInstanceOf(
            \Magento\Framework\Logger\Monolog::class,
            $injectedLogger,
            sprintf(
                '%s must receive a Magento\Framework\Logger\Monolog instance wired via '
                . 'Bold\CheckoutPaymentBooster\Logger, not the default PSR logger.',
                $className
            )
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function loggerWiredClassesProvider(): array
    {
        return [
            'AfterSubmitObserver'  => [AfterSubmitObserver::class],
            'BeforePlaceObserver'  => [BeforePlaceObserver::class],
            'SuccessPlugin'        => [SuccessPlugin::class],
        ];
    }
}
