<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\Controller\Digitalwallets\Checkout;

use Bold\CheckoutPaymentBooster\Model\CheckoutData;
use Laminas\Http\Header\GenericHeader;
use Laminas\Http\Headers;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * @magentoAppIsolation enabled
 */
class GetPaymentGatewaysTest extends AbstractController
{
    /**
     * @magentoAppArea frontend
     */
    public function testGetsPaymentGateways(): void
    {
        $boldCheckoutDataStub = $this->createStub(CheckoutData::class);
        $objectManager = Bootstrap::getObjectManager();
        /** @var FormKey $formKey */
        $formKey = $objectManager->get(FormKey::class);
        /** @var HttpRequest $request */
        $request = $this->getRequest();
        /** @var SerializerInterface $serializer */
        $serializer = $objectManager->create(SerializerInterface::class);

        $boldCheckoutDataStub
            ->method('getPublicOrderId')
            ->willReturn('5fdbcd5399a94b9999a3b498693e4fa31194ed1781c9459ebcf2c67d5800a042');
        $boldCheckoutDataStub
            ->method('getPaymentGateways')
            ->willReturn(
                [
                    [
                        'id' => 42,
                        'auth_token' => 'NmJkZjViYzYtNWI0MC00Y2RlLTg0N2ItMDExYWE2NGU3ZDdi',
                        'currency' => 'USD',
                    ]
                ]
            );

        $objectManager->configure(
            [
                CheckoutData::class => [
                    'shared' => true
                ]
            ]
        );
        // @phpstan-ignore-next-line
        $objectManager->addSharedInstance($boldCheckoutDataStub, CheckoutData::class);

        $request
            ->setMethod(HttpRequest::METHOD_POST)
            ->setPostValue(
                [
                    'form_key' => $formKey->getFormKey(),
                ]
            );

        /** @var Headers $headers */
        $headers = $request->getHeaders();

        $headers->addHeader(GenericHeader::fromString('X-Requested-With:XMLHttpRequest'));

        $this->dispatch('bold_booster/digitalwallets_checkout/getPaymentGateways');

        /** @var HttpResponse $response */
        $response = $this->getResponse();
        /** @var mixed[] $responseBody */
        $responseBody = $serializer->unserialize($response->getBody());

        self::assertSame(
            [
                [
                    'id' => 42,
                    'auth_token' => 'NmJkZjViYzYtNWI0MC00Y2RlLTg0N2ItMDExYWE2NGU3ZDdi',
                    'currency' => 'USD',
                ]
            ],
            $responseBody
        );
    }

    /**
     * @dataProvider doesNotGetPaymentGatewaysDataProvider
     * @magentoAppArea frontend
     */
    public function testDoesNotGetPaymentGateways(bool $isPost, bool $withFormKey, bool $isAjax): void
    {
        $boldCheckoutDataStub = $this->createStub(CheckoutData::class);
        $objectManager = Bootstrap::getObjectManager();
        /** @var FormKey $formKey */
        $formKey = $objectManager->get(FormKey::class);
        /** @var HttpRequest $request */
        $request = $this->getRequest();

        $boldCheckoutDataStub
            ->method('getPublicOrderId')
            ->willReturn(null);

        $objectManager->configure(
            [
                CheckoutData::class => [
                    'shared' => true
                ]
            ]
        );
        // @phpstan-ignore-next-line
        $objectManager->addSharedInstance($boldCheckoutDataStub, CheckoutData::class);

        if ($isPost) {
            $method = HttpRequest::METHOD_POST;
        } else {
            $method = HttpRequest::METHOD_GET;
        }

        $request
            ->setMethod($method)
            ->setPostValue(
                [
                    'form_key' => $withFormKey ? $formKey->getFormKey() : null,
                ]
            );

        if ($isAjax) {
            /** @var Headers $headers */
            $headers = $request->getHeaders();

            $headers->addHeader(GenericHeader::fromString('X-Requested-With:XMLHttpRequest'));
        }

        $this->dispatch('bold_booster/digitalwallets_checkout/getPaymentGateways');

        /** @var HttpResponse $response */
        $response = $this->getResponse();
        $responseBody = $response->getBody();

        if ($responseBody !== '') {
            $responseBody = json_decode($responseBody, true);
        }

        self::assertEmpty($responseBody);
    }

    /**
     * @return array<string, array<string, bool>>
     */
    public function doesNotGetPaymentGatewaysDataProvider(): array
    {
        return [
            'if not a POST request' => [
                'isPost' => false,
                'withFormKey' => true,
                'isAjax' => true
            ],
            'if not an AJAX request' => [
                'isPost' => true,
                'withFormKey' => true,
                'isAjax' => false
            ],
            'if form key not provided' => [
                'isPost' => true,
                'withFormKey' => false,
                'isAjax' => true
            ],
            'if Bold order ID not set' => [
                'isPost' => true,
                'withFormKey' => true,
                'isAjax' => true
            ],
        ];
    }
}
