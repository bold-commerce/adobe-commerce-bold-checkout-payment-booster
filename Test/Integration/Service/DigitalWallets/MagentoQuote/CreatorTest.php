<?php

/** @noinspection PhpLanguageLevelInspection */

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
        $productRepository = $objectManager->create(ProductRepositoryInterface::class);
        $product = $productRepository->get('simple');
        $productOptions = [];
        $dateTime = new DateTimeImmutable();
        $productRequestData = [
            'product' => $product->getId(),
        ];
        $storeManager = $objectManager->get(StoreManagerInterface::class);
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
                    $values = $productOption->getValues() ?? [];
                    $valueKeys = array_keys($values);
                    $productOptions[$productOption->getOptionId()] = $valueKeys[array_rand($valueKeys)];
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

    // other methods remain unchanged

    // The rest of the test methods you included earlier are assumed to follow and remain unchanged
}
