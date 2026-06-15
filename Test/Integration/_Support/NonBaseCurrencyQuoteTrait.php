<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\_Support;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;

use function reset;

trait NonBaseCurrencyQuoteTrait
{
    /**
     * @return array<string, array{string}>
     */
    public function nonBaseDisplayCurrencyProvider(): array
    {
        $cases = [];

        foreach (NonBaseCurrencyTestConfig::NON_BASE_DISPLAY_CURRENCIES as $displayCurrency) {
            $cases[$displayCurrency] = [$displayCurrency];
        }

        return $cases;
    }

    protected function applyDisplayCurrencyToQuote(
        Quote $quote,
        string $displayCurrency,
        string $baseCurrency = NonBaseCurrencyTestConfig::BASE_CURRENCY
    ): Quote {
        $this->setStoreDisplayCurrency($displayCurrency, (int) $quote->getStoreId());

        $store = $quote->getStore();
        $store->unsetData('current_currency');
        $store->setCurrentCurrencyCode($displayCurrency);
        $quote->setBaseCurrencyCode($baseCurrency);
        $quote->setQuoteCurrencyCode($displayCurrency);
        $quote->setStoreCurrencyCode($displayCurrency);
        $quote->setTotalsCollectedFlag(false);

        if (!$quote->getIsVirtual()) {
            $quote->getShippingAddress()->setCollectShippingRates(true);
        }

        $quote->collectTotals();

        return $quote;
    }

    protected function loadQuoteByReservedOrderId(string $reservedOrderId): Quote
    {
        $this->resetQuoteRepositoryCache();

        $objectManager = Bootstrap::getObjectManager();
        $searchCriteria = $objectManager->create(SearchCriteriaBuilder::class)
            ->addFilter('reserved_order_id', $reservedOrderId)
            ->create();
        $quotes = $objectManager->get(CartRepositoryInterface::class)
            ->getList($searchCriteria)
            ->getItems();

        self::assertNotEmpty($quotes, 'Fixture quote with reserved_order_id "' . $reservedOrderId . '" not found');

        return $objectManager->get(CartRepositoryInterface::class)->get((int) reset($quotes)->getId());
    }

    protected function hydrateQuoteProducts(Quote $quote): Quote
    {
        $objectManager = Bootstrap::getObjectManager();
        $productRepository = $objectManager->get(ProductRepositoryInterface::class);

        foreach ($quote->getAllItems() as $item) {
            if (!$item->getProduct()) {
                $item->setProduct($productRepository->getById((int) $item->getProductId()));
            }
        }

        return $quote;
    }

    protected function prepareQuoteWithDisplayCurrency(
        string $reservedOrderId,
        string $displayCurrency,
        bool $persist = false
    ): Quote {
        $this->resetQuoteRepositoryCache();
        $quote = $this->hydrateQuoteProducts($this->loadQuoteByReservedOrderId($reservedOrderId));
        $quote = $this->applyDisplayCurrencyToQuote($quote, $displayCurrency);

        return $persist ? $this->persistQuote($quote) : $quote;
    }

    protected function persistQuote(Quote $quote): Quote
    {
        $objectManager = Bootstrap::getObjectManager();
        $cartRepository = $objectManager->get(CartRepositoryInterface::class);
        $quoteId = (int) $quote->getId();
        $cartRepository->save($quote);
        $this->resetQuoteRepositoryCache();

        return $cartRepository->get($quoteId);
    }

    protected function setStoreDisplayCurrency(string $displayCurrency, ?int $storeId = null): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $storeManager = $objectManager->get(StoreManagerInterface::class);
        $store = $storeId === null ? $storeManager->getStore() : $storeManager->getStore($storeId);
        $httpContext = $objectManager->get(HttpContext::class);

        $httpContext->unsValue(HttpContext::CONTEXT_CURRENCY);
        $store->unsetData('available_currency_codes');
        $store->unsetData('current_currency');
        $store->setCurrentCurrencyCode($displayCurrency);
    }

    protected function resetQuoteRepositoryCache(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $cartRepository = $objectManager->get(CartRepositoryInterface::class);

        if (method_exists($cartRepository, '_resetState')) {
            $cartRepository->_resetState();
        }
    }

    private function assertQuoteUsesNonBaseCurrency(
        CartInterface $quote,
        string $displayCurrency,
        string $baseCurrency = NonBaseCurrencyTestConfig::BASE_CURRENCY
    ): void {
        self::assertSame($displayCurrency, $quote->getQuoteCurrencyCode());
        self::assertSame($baseCurrency, $quote->getBaseCurrencyCode());
        self::assertNotSame($quote->getQuoteCurrencyCode(), $quote->getBaseCurrencyCode());
    }

    private function assertDisplayAndBaseGrandTotalsDiffer(CartInterface $quote): void
    {
        self::assertNotEquals(
            (float) $quote->getGrandTotal(),
            (float) $quote->getBaseGrandTotal(),
            'Display and base grand totals must differ for non-base currency quotes'
        );
    }

    /**
     * @param array<string, mixed> $orderData
     */
    private function assertOrderDataUsesBaseCurrency(
        array $orderData,
        string $baseCurrency = NonBaseCurrencyTestConfig::BASE_CURRENCY
    ): void {
        self::assertSame($baseCurrency, $orderData['amount']['currency_code'] ?? null);
        self::assertSame($baseCurrency, $orderData['item_total']['currency_code'] ?? null);
        self::assertSame($baseCurrency, $orderData['tax_total']['currency_code'] ?? null);
        self::assertSame($baseCurrency, $orderData['discount']['currency_code'] ?? null);

        if (isset($orderData['selected_shipping_option']['amount']['currency_code'])) {
            self::assertSame(
                $baseCurrency,
                $orderData['selected_shipping_option']['amount']['currency_code']
            );
        }
    }
}
