<?php

/** @noinspection PhpLanguageLevelInspection */

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\Controller\Digitalwallets\Quote;

use DateTimeImmutable;
use Exception;
use Magento\Bundle\Api\Data\LinkInterface;
use Magento\Bundle\Api\Data\OptionInterface as BundleOptionInterface;
use Magento\Bundle\Api\ProductOptionRepositoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Api\Data\OptionInterface as ConfigurableOptionInterface;
use Magento\ConfigurableProduct\Api\OptionRepositoryInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\AbstractController;

use function array_rand;
use function reset;

class CreateTest extends AbstractController
{
    /**
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     */
    public function testCreatesQuoteSuccessfullyForSimpleProduct(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var FormKey $formKey */
        $formKey = $objectManager->get(FormKey::class);
        /** @var ProductRepositoryInterface $productRepository */
        $productRepository = $objectManager->create(ProductRepositoryInterface::class);
        $product = $productRepository->get('simple');
        $productOptions = [];
        $dateTime = new DateTimeImmutable();
        /** @var HttpRequest $request */
        $request = $this->getRequest();
        /** @var SerializerInterface $serializer */
        $serializer = $objectManager->create(SerializerInterface::class);

        foreach ($product->getOptions() ?? [] as $productOption) {
            $productOptions[$productOption->getOptionId()] = match ($productOption->getType()) {
                'field' => 'test',
                'date_time' => [
                    'day' => $dateTime->format('d'),
                    'month' => $dateTime->format('m'),
                    'year' => $dateTime->format('Y'),
                    'hour' => $dateTime->format('H'),
                    'minute' => $dateTime->format('i'),
                ],
                'drop_down', 'radio' => array_rand($productOption->getValues() ?? []),
                default => throw new Exception('Unsupported product option type "' . $productOption->getType() . '"')
            };
        }

        $request->setMethod(HttpRequest::METHOD_POST)
            ->setPostValue(
                [
                    'form_key' => $formKey->getFormKey(),
                    'product' => $product->getId(),
                    'options' => $productOptions
                ]
            );

        $this->dispatch('bold_booster/digitalwallets_quote/create');

        /** @var HttpResponse $response */
        $response = $this->getResponse();
        /** @var mixed[] $responseBody */
        $responseBody = $serializer->unserialize($response->getBody());

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($responseBody['success']);
        self::assertNotEmpty($responseBody['quoteData']);
    }

    /**
     * @magentoDataFixture Magento/ConfigurableProduct/_files/configurable_product_with_one_simple.php
     */
    public function testCreatesQuoteSuccessfullyForConfigurableProduct(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var FormKey $formKey */
        $formKey = $objectManager->get(FormKey::class);
        /** @var ProductRepositoryInterface $productRepository */
        $productRepository = $objectManager->create(ProductRepositoryInterface::class);
        /** @var ProductInterface&Product $product */
        $product = $productRepository->get('configurable');
        /** @var HttpRequest $request */
        $request = $this->getRequest();
        /** @var OptionRepositoryInterface $productOptionRepository */
        $productOptionRepository = $objectManager->create(OptionRepositoryInterface::class);
        $productOptions = $productOptionRepository->getList($product->getSku());
        /** @var ConfigurableOptionInterface $productOption */
        $productOption = reset($productOptions);
        /** @var SerializerInterface $serializer */
        $serializer = $objectManager->create(SerializerInterface::class);

        $request->setMethod(HttpRequest::METHOD_POST)
            ->setPostValue(
                [
                    'form_key' => $formKey->getFormKey(),
                    'product' => $product->getId(),
                    'super_attribute' => [
                        // @phpstan-ignore-next-line
                        $productOption->getAttributeId() => $productOption->getValues()[0]
                            ->getValueIndex()
                    ]
                ]
            );

        $this->dispatch('bold_booster/digitalwallets_quote/create');

        /** @var HttpResponse $response */
        $response = $this->getResponse();
        /** @var mixed[] $responseBody */
        $responseBody = $serializer->unserialize($response->getBody());

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($responseBody['success']);
        self::assertNotEmpty($responseBody['quoteData']);
    }

    /**
     * @magentoDataFixture Magento/Bundle/_files/bundle_product_dropdown_options.php
     */
    public function testCreatesQuoteSuccessfullyForBundleProduct(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var FormKey $formKey */
        $formKey = $objectManager->get(FormKey::class);
        /** @var ProductRepositoryInterface $productRepository */
        $productRepository = $objectManager->create(ProductRepositoryInterface::class);
        /** @var ProductInterface&Product $product */
        $product = $productRepository->get('bundle-product-dropdown-options');
        /** @var HttpRequest $request */
        $request = $this->getRequest();
        /** @var ProductOptionRepositoryInterface $productOptionRepository */
        $productOptionRepository = $objectManager->create(ProductOptionRepositoryInterface::class);
        $productOptions = $productOptionRepository->getList($product->getSku());
        /** @var BundleOptionInterface $productOption */
        $productOption = reset($productOptions);
        $productLinks = $productOption->getProductLinks() ?? [];
        /** @var LinkInterface $productLink */
        $productLink = reset($productLinks);
        /** @var SerializerInterface $serializer */
        $serializer = $objectManager->create(SerializerInterface::class);

        $request->setMethod(HttpRequest::METHOD_POST)
            ->setPostValue(
                [
                    'form_key' => $formKey->getFormKey(),
                    'product' => $product->getId(),
                    'bundle_options_data' => [
                        $productOption->getOptionId() => [
                            $productLink->getId() => 1
                        ]
                    ]
                ]
            );

        $this->dispatch('bold_booster/digitalwallets_quote/create');

        /** @var HttpResponse $response */
        $response = $this->getResponse();
        /** @var mixed[] $responseBody */
        $responseBody = $serializer->unserialize($response->getBody());

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($responseBody['success']);
        self::assertNotEmpty($responseBody['quoteData']);
    }

    /**
     * @magentoDataFixture Magento/GroupedProduct/_files/product_grouped.php
     */
    public function testCreatesQuoteSuccessfullyForGroupedProduct(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var FormKey $formKey */
        $formKey = $objectManager->get(FormKey::class);
        /** @var ProductRepositoryInterface $productRepository */
        $productRepository = $objectManager->create(ProductRepositoryInterface::class);
        /** @var ProductInterface&Product $product */
        $product = $productRepository->get('grouped-product');
        /** @var HttpRequest $request */
        $request = $this->getRequest();
        $associatedProducts = $product->getTypeInstance()->getAssociatedProducts($product);
        $superGroup = [];
        /** @var SerializerInterface $serializer */
        $serializer = $objectManager->create(SerializerInterface::class);

        foreach ($associatedProducts as $associatedProduct) {
            $superGroup[$associatedProduct->getId()] = 1;
        }

        $request->setMethod(HttpRequest::METHOD_POST)
            ->setPostValue(
                [
                    'form_key' => $formKey->getFormKey(),
                    'product' => $product->getId(),
                    'super_group' => $superGroup
                ]
            );

        $this->dispatch('bold_booster/digitalwallets_quote/create');

        /** @var HttpResponse $response */
        $response = $this->getResponse();
        /** @var mixed[] $responseBody */
        $responseBody = $serializer->unserialize($response->getBody());

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($responseBody['success']);
        self::assertNotEmpty($responseBody['quoteData']);
    }

    public function testReturnsErrorIfCannotCreateQuote(): void
    {
        self::markTestIncomplete('Work in progress');
        //$objectManager = Bootstrap::getObjectManager();
    }
}
