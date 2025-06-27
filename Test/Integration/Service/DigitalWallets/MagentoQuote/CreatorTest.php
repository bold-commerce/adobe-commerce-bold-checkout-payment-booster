<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\Service\DigitalWallets\MagentoQuote;

use Bold\CheckoutPaymentBooster\Service\DigitalWallets\MagentoQuote\Creator;
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
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\CartExtension;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\CartInterfaceFactory;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

use function __;
use function array_rand;
use function reset;

class CreatorTest extends TestCase
{
    /**
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     */
    public function testCreatesQuoteSuccessfullyForSimpleProduct(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var ProductRepositoryInterface $productRepository */
        $productRepository = $objectManager->create(ProductRepositoryInterface::class);
        $product = $productRepository->get('simple');
        $productOptions = [];
        $dateTime = new DateTimeImmutable();
        $productRequestData = [
            'product' => $product->getId(),
        ];
        /** @var StoreManagerInterface $storeManager */
        $storeManager = $objectManager->get(StoreManagerInterface::class);
        /** @var Creator $magentoQuoteCreator */
        $magentoQuoteCreator = $objectManager->create(Creator::class);

        foreach ($product->getOptions() ?? [] as $productOption) {
            switch ($productOption->getType()) {
                case 'field':
                    $productOptions[$productOption->getOptionId()] = 'test';

                    break;
                case 'date_time':
                    $productOptions[$productOption->getOptionId()] = [
                        'day' => $dateTime->format('d'),
                        'month' => $dateTime->format('m'),
                        'year' => $dateTime->format('Y'),
                        'hour' => $dateTime->format('H'),
                        'minute' => $dateTime->format('i'),
                    ];

                    break;
                case 'drop_down':
                case 'radio':
                    $productOptions[$productOption->getOptionId()] = array_rand($productOption->getValues() ?? []);

                    break;
                default:
                    throw new Exception('Unsupported product option type "' . $productOption->getType() . '"');
            }
        }

        $productRequestData['options'] = $productOptions;

        ['quote' => $quote, 'maskedId' => $maskedId] = $magentoQuoteCreator->createQuote(
            $storeManager->getStore()->getId(),
            $product,
            $productRequestData
        );

        self::assertInstanceOf(CartInterface::class, $quote);
        self::assertNotNull($quote->getId());
        self::assertNotEmpty($maskedId);
    }

    /**
     * @magentoDataFixture Magento/ConfigurableProduct/_files/configurable_product_with_one_simple.php
     */
    public function testCreatesQuoteSuccessfullyForConfigurableProduct(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var ProductRepositoryInterface $productRepository */
        $productRepository = $objectManager->create(ProductRepositoryInterface::class);
        /** @var ProductInterface&Product $product */
        $product = $productRepository->get('configurable');
        /** @var OptionRepositoryInterface $productOptionRepository */
        $productOptionRepository = $objectManager->create(OptionRepositoryInterface::class);
        $productOptions = $productOptionRepository->getList($product->getSku());
        /** @var ConfigurableOptionInterface $productOption */
        $productOption = reset($productOptions);
        $productRequestData = [
            'product' => $product->getId(),
            'super_attribute' => [
                $productOption->getAttributeId() => $productOption
                    ->getValues()[0]
                    ->getValueIndex()
            ]
        ];
        /** @var StoreManagerInterface $storeManager */
        $storeManager = $objectManager->get(StoreManagerInterface::class);
        /** @var Creator $magentoQuoteCreator */
        $magentoQuoteCreator = $objectManager->create(Creator::class);

        ['quote' => $quote, 'maskedId' => $maskedId] = $magentoQuoteCreator->createQuote(
            $storeManager->getStore()->getId(),
            $product,
            $productRequestData
        );

        self::assertInstanceOf(CartInterface::class, $quote);
        self::assertNotNull($quote->getId());
        self::assertNotEmpty($maskedId);
    }

    /**
     * @magentoDataFixture Magento/Bundle/_files/bundle_product_dropdown_options.php
     */
    public function testCreatesQuoteSuccessfullyForBundleProduct(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var ProductRepositoryInterface $productRepository */
        $productRepository = $objectManager->create(ProductRepositoryInterface::class);
        /** @var ProductInterface&Product $product */
        $product = $productRepository->get('bundle-product-dropdown-options');
        /** @var ProductOptionRepositoryInterface $productOptionRepository */
        $productOptionRepository = $objectManager->create(ProductOptionRepositoryInterface::class);
        $productOptions = $productOptionRepository->getList($product->getSku());
        /** @var BundleOptionInterface $productOption */
        $productOption = reset($productOptions);
        $productLinks = $productOption->getProductLinks() ?? [];
        /** @var LinkInterface $productLink */
        $productLink = reset($productLinks);
        $productRequestData = [
            'product' => $product->getId(),
            'bundle_options_data' => [
                $productOption->getOptionId() => [
                    $productLink->getId() => 1
                ]
            ]
        ];
        /** @var StoreManagerInterface $storeManager */
        $storeManager = $objectManager->get(StoreManagerInterface::class);
        /** @var Creator $magentoQuoteCreator */
        $magentoQuoteCreator = $objectManager->create(Creator::class);

        ['quote' => $quote, 'maskedId' => $maskedId] = $magentoQuoteCreator->createQuote(
            $storeManager->getStore()->getId(),
            $product,
            $productRequestData
        );

        self::assertInstanceOf(CartInterface::class, $quote);
        self::assertNotNull($quote->getId());
        self::assertNotEmpty($maskedId);
    }

    /**
     * @magentoDataFixture Magento/GroupedProduct/_files/product_grouped.php
     */
    public function testCreatesQuoteSuccessfullyForGroupedProduct(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var ProductRepositoryInterface $productRepository */
        $productRepository = $objectManager->create(ProductRepositoryInterface::class);
        /** @var ProductInterface&Product $product */
        $product = $productRepository->get('grouped-product');
        $associatedProducts = $product->getTypeInstance()->getAssociatedProducts($product);
        $superGroup = [];
        $productRequestData = [
            'product' => $product->getId(),
        ];
        /** @var StoreManagerInterface $storeManager */
        $storeManager = $objectManager->get(StoreManagerInterface::class);
        /** @var Creator $magentoQuoteCreator */
        $magentoQuoteCreator = $objectManager->create(Creator::class);

        foreach ($associatedProducts as $associatedProduct) {
            $superGroup[$associatedProduct->getId()] = 1;
        }

        $productRequestData['super_group'] = $superGroup;

        ['quote' => $quote, 'maskedId' => $maskedId] = $magentoQuoteCreator->createQuote(
            $storeManager->getStore()->getId(),
            $product,
            $productRequestData
        );

        self::assertInstanceOf(CartInterface::class, $quote);
        self::assertNotNull($quote->getId());
        self::assertNotEmpty($maskedId);
    }

    public function testThrowsExceptionIfProductCannotBeAddedToQuote(): void
    {
        $this->expectExceptionMessage('Could not add product to quote. Error: "Unknown error"');

        $quoteStub = $this->createStub(Quote::class);
        $objectManager = Bootstrap::getObjectManager();
        /** @var CartExtension $cartExtensionAttributes */
        $cartExtensionAttributes = $objectManager->create(CartExtension::class);
        /** @var LocalizedException $localizedException */
        $localizedException = $objectManager->create(
            LocalizedException::class,
            [
                'phrase' => __('Unknown error')
            ]
        );
        $quoteFactoryStub = $this->createStub(CartInterfaceFactory::class);
        /** @var StoreManagerInterface $storeManager */
        $storeManager = $objectManager->get(StoreManagerInterface::class);
        /** @var ProductInterface $product */
        $product = $objectManager->create(ProductInterface::class);
        /** @var Creator $magentoQuoteCreator */
        $magentoQuoteCreator = $objectManager->create(
            Creator::class,
            [
                'quoteFactory' => $quoteFactoryStub
            ]
        );

        $quoteStub
            ->method('getExtensionAttributes')
            ->willReturn($cartExtensionAttributes);
        $quoteStub
            ->method('addProduct')
            ->willThrowException($localizedException);

        $quoteFactoryStub
            ->method('create')
            ->willReturn($quoteStub);

        $magentoQuoteCreator->createQuote(
            $storeManager->getStore()->getId(),
            $product,
            []
        );
    }

    public function testThrowsExceptionIfAddProductReturnsErrorMessage(): void
    {
        $this->expectExceptionMessage('Invalid or missing product option');

        $quoteStub = $this->createStub(Quote::class);
        $objectManager = Bootstrap::getObjectManager();
        /** @var CartExtension $cartExtensionAttributes */
        $cartExtensionAttributes = $objectManager->create(CartExtension::class);
        $quoteFactoryStub = $this->createStub(CartInterfaceFactory::class);
        /** @var StoreManagerInterface $storeManager */
        $storeManager = $objectManager->get(StoreManagerInterface::class);
        /** @var ProductInterface $product */
        $product = $objectManager->create(ProductInterface::class);
        /** @var Creator $magentoQuoteCreator */
        $magentoQuoteCreator = $objectManager->create(
            Creator::class,
            [
                'quoteFactory' => $quoteFactoryStub,
            ]
        );

        $quoteStub
            ->method('getExtensionAttributes')
            ->willReturn($cartExtensionAttributes);
        $quoteStub
            ->method('addProduct')
            ->willReturn('Invalid or missing product option');

        $quoteFactoryStub
            ->method('create')
            ->willReturn($quoteStub);

        $magentoQuoteCreator->createQuote(
            $storeManager->getStore()->getId(),
            $product,
            []
        );
    }

    public function testThrowsExceptionIfQuoteCannotBeCreated(): void
    {
        $this->expectExceptionMessage('Could not save quote. Error: "Unknown error"');

        $quoteStub = $this->createStub(Quote::class);
        $objectManager = Bootstrap::getObjectManager();
        /** @var CartExtension $cartExtensionAttributes */
        $cartExtensionAttributes = $objectManager->create(CartExtension::class);
        /** @var CartItemInterface $quoteItem */
        $quoteItem = $objectManager->create(CartItemInterface::class);
        /** @var AddressInterface $shippingAddress */
        $shippingAddress = $objectManager->create(AddressInterface::class);
        $quoteFactoryStub = $this->createStub(CartInterfaceFactory::class);
        $quoteRepositoryStub = $this->createStub(CartRepositoryInterface::class);
        /** @var LocalizedException $localizedException */
        $localizedException = $objectManager->create(
            LocalizedException::class,
            [
                'phrase' => __('Unknown error')
            ]
        );
        /** @var StoreManagerInterface $storeManager */
        $storeManager = $objectManager->get(StoreManagerInterface::class);
        /** @var ProductInterface $product */
        $product = $objectManager->create(ProductInterface::class);
        /** @var Creator $magentoQuoteCreator */
        $magentoQuoteCreator = $objectManager->create(
            Creator::class,
            [
                'quoteFactory' => $quoteFactoryStub,
                'quoteRepository' => $quoteRepositoryStub,
            ]
        );

        $quoteStub
            ->method('getExtensionAttributes')
            ->willReturn($cartExtensionAttributes);
        $quoteStub
            ->method('addProduct')
            ->willReturn($quoteItem);
        $quoteStub
            ->method('getShippingAddress')
            ->willReturn($shippingAddress);

        $quoteFactoryStub
            ->method('create')
            ->willReturn($quoteStub);

        $quoteRepositoryStub
            ->method('save')
            ->willThrowException($localizedException);

        $magentoQuoteCreator->createQuote(
            $storeManager->getStore()->getId(),
            $product,
            []
        );
    }

    public function testThrowsExceptionIfQuoteMaskIdCannotBeCreated(): void
    {
        $this->expectExceptionMessage(
            'Could not create mask identifier for quote with identifier "42". Error: "Invalid quote ID"'
        );

        $quoteStub = $this->createStub(Quote::class);
        $objectManager = Bootstrap::getObjectManager();
        /** @var CartExtension $cartExtensionAttributes */
        $cartExtensionAttributes = $objectManager->create(CartExtension::class);
        /** @var CartItemInterface $quoteItem */
        $quoteItem = $objectManager->create(CartItemInterface::class);
        /** @var AddressInterface $shippingAddress */
        $shippingAddress = $objectManager->create(AddressInterface::class);
        $quoteFactoryStub = $this->createStub(CartInterfaceFactory::class);
        $quoteIdMaskStub = $this
            ->getMockBuilder(QuoteIdMask::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['save'])
            ->addMethods(['setQuoteId'])
            ->getMock();
        $quoteIdMaskFactoryStub = $this->createStub(QuoteIdMaskFactory::class);
        /** @var LocalizedException $localizedException */
        $localizedException = $objectManager->create(
            LocalizedException::class,
            [
                'phrase' => __('Invalid quote ID')
            ]
        );
        $quoteRepositoryStub = $this->createStub(CartRepositoryInterface::class);
        /** @var StoreManagerInterface $storeManager */
        $storeManager = $objectManager->get(StoreManagerInterface::class);
        /** @var ProductInterface $product */
        $product = $objectManager->create(ProductInterface::class);
        /** @var Creator $magentoQuoteCreator */
        $magentoQuoteCreator = $objectManager->create(
            Creator::class,
            [
                'quoteFactory' => $quoteFactoryStub,
                'quoteRepository' => $quoteRepositoryStub,
                'quoteIdMaskFactory' => $quoteIdMaskFactoryStub,
            ]
        );

        $quoteStub
            ->method('getExtensionAttributes')
            ->willReturn($cartExtensionAttributes);
        $quoteStub
            ->method('addProduct')
            ->willReturn($quoteItem);
        $quoteStub
            ->method('getShippingAddress')
            ->willReturn($shippingAddress);
        $quoteStub
            ->method('getId')
            ->willReturn(42);

        $quoteFactoryStub
            ->method('create')
            ->willReturn($quoteStub);

        $quoteIdMaskStub
            ->method('setQuoteId')
            ->willReturnSelf();
        $quoteIdMaskStub
            ->method('save')
            ->willThrowException($localizedException);

        $quoteIdMaskFactoryStub
            ->method('create')
            ->willReturn($quoteIdMaskStub);

        $magentoQuoteCreator->createQuote(
            $storeManager->getStore()->getId(),
            $product,
            []
        );
    }

    public function testThrowsExceptionIfQuoteCannotBeDeactivated(): void
    {
        $this->expectExceptionMessage('Could not deactivate quote. Error: "Invalid quote ID"');

        $quoteStub = $this->createStub(Quote::class);
        $objectManager = Bootstrap::getObjectManager();
        /** @var CartExtension $cartExtensionAttributes */
        $cartExtensionAttributes = $objectManager->create(CartExtension::class);
        /** @var CartItemInterface $quoteItem */
        $quoteItem = $objectManager->create(CartItemInterface::class);
        /** @var AddressInterface $shippingAddress */
        $shippingAddress = $objectManager->create(AddressInterface::class);
        $quoteFactoryStub = $this->createStub(CartInterfaceFactory::class);
        $quoteIdMaskStub = $this
            ->getMockBuilder(QuoteIdMask::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['save'])
            ->addMethods(['setQuoteId'])
            ->getMock();
        $quoteIdMaskFactoryStub = $this->createStub(QuoteIdMaskFactory::class);
        $quoteRepositoryMock = $this->createMock(CartRepositoryInterface::class);
        $matcher = self::exactly(2);
        /** @var LocalizedException $localizedException */
        $localizedException = $objectManager->create(
            LocalizedException::class,
            [
                'phrase' => __('Invalid quote ID')
            ]
        );
        /** @var StoreManagerInterface $storeManager */
        $storeManager = $objectManager->get(StoreManagerInterface::class);
        /** @var ProductInterface $product */
        $product = $objectManager->create(ProductInterface::class);
        /** @var Creator $magentoQuoteCreator */
        $magentoQuoteCreator = $objectManager->create(
            Creator::class,
            [
                'quoteFactory' => $quoteFactoryStub,
                'quoteRepository' => $quoteRepositoryMock,
                'quoteIdMaskFactory' => $quoteIdMaskFactoryStub,
            ]
        );

        $quoteStub
            ->method('getExtensionAttributes')
            ->willReturn($cartExtensionAttributes);
        $quoteStub
            ->method('addProduct')
            ->willReturn($quoteItem);
        $quoteStub
            ->method('getShippingAddress')
            ->willReturn($shippingAddress);
        $quoteStub
            ->method('getId')
            ->willReturn(42);

        $quoteFactoryStub
            ->method('create')
            ->willReturn($quoteStub);

        /** @noinspection MockingMethodsCorrectnessInspection */
        $quoteIdMaskStub
            ->method('setQuoteId')
            ->willReturnSelf();
        $quoteIdMaskStub
            ->method('save')
            ->willThrowException($localizedException);

        $quoteIdMaskFactoryStub
            ->method('create')
            ->willReturn($quoteIdMaskStub);

        if ($this->getMagentoVersion() != '2.4.8' && $this->getMagentoVersion() != '2.4.8-p1') {
            $quoteRepositoryMock
                ->expects($matcher)
                ->method('save')
                ->willReturnCallback(
                    function () use ($matcher, $localizedException) {
                        if ($matcher->getInvocationCount() !== 2) {
                            return;
                        }

                        throw $localizedException;
                    }
                );
        } else {
            //In PHPUnit 10, the getInvocationCount() got renamed to numberOfInvocations().
            $quoteRepositoryMock
                ->expects($matcher)
                ->method('save')
                ->willReturnCallback(
                    function () use ($matcher, $localizedException) {
                        if ($matcher->numberOfInvocations() !== 2) {
                            return;
                        }

                        throw $localizedException;
                    }
                );
        }

        $magentoQuoteCreator->createQuote(
            $storeManager->getStore()->getId(),
            $product,
            []
        );
    }

    /**
     * Get current magento version
     *
     * @return string
     */
    private function getMagentoVersion(): string
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var ProductRepositoryInterface $productRepository */
        $productMetaData = $objectManager->create(\Magento\Framework\App\ProductMetadataInterface::class);
        return $productMetaData->getVersion();
    }
}
