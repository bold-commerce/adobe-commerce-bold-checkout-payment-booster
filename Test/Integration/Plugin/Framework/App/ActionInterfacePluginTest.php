<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\Plugin\Framework\App;

use Bold\CheckoutPaymentBooster\Plugin\Framework\App\ActionInterfacePlugin;
use Bold\CheckoutPaymentBooster\Test\Integration\_Assertions\AssertPluginIsConfiguredCorrectly;
use Magento\Customer\Model\ResourceModel\CustomerRepository;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\App\TestStubs\InterfaceOnlyFrontendAction;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class ActionInterfacePluginTest extends TestCase
{
    use AssertPluginIsConfiguredCorrectly;

    /**
     * @magentoAppArea frontend
     */
    public function testIsConfiguredCorrectly(): void
    {
        self::assertPluginIsConfiguredCorrectly(
            'bold_booster_customer_id_context',
            ActionInterfacePlugin::class,
            ActionInterface::class,
            15
        );
    }

    /**
     * @magentoAppArea frontend
     * @magentoDataFixture Magento/Customer/_files/customer.php
     */
    public function testSetsCustomerIdInHttpContext(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var CustomerRepository $customerRepository */
        $customerRepository = $objectManager->create(CustomerRepository::class);
        $customer = $customerRepository->get('customer@example.com');
        /** @var CustomerSession $customerSession */
        $customerSession = $objectManager->get(CustomerSession::class);
        /** @var InterfaceOnlyFrontendAction $action */
        $action = $objectManager->create(InterfaceOnlyFrontendAction::class); // @phpstan-ignore-line
        /** @var HttpContext $httpContext */
        $httpContext = $objectManager->get(HttpContext::class);

        $customerSession->setCustomerId($customer->getId());

        $action->execute(); // @phpstan-ignore-line

        self::assertSame($customer->getId(), $httpContext->getValue('customer_id'));
    }
}
