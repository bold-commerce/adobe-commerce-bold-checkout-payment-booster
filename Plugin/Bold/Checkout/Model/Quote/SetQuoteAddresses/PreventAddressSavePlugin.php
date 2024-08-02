<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Plugin\Bold\Checkout\Model\Quote\SetQuoteAddresses;

use Bold\Checkout\Api\Data\Quote\ResultInterface;
use Bold\Checkout\Model\Quote\LoadAndValidate;
use Bold\Checkout\Model\Quote\QuoteExtensionDataFactory;
use Bold\Checkout\Model\Quote\Result\Builder;
use Bold\Checkout\Model\Quote\SetQuoteAddresses;
use Bold\Checkout\Model\ResourceModel\Quote\QuoteExtensionData;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\Data\AddressInterface;

class PreventAddressSavePlugin
{
    /**
     * @var Builder
     */
    private $quoteResultBuilder;

    /**
     * @var LoadAndValidate
     */
    private $loadAndValidate;

    /**
     * @var QuoteExtensionDataFactory
     */
    private $quoteExtensionDataFactory;

    /**
     * @var QuoteExtensionData
     */
    private $quoteExtensionDataResource;

    /**
     * @param Builder $quoteResultBuilder
     * @param LoadAndValidate $loadAndValidate
     * @param QuoteExtensionDataFactory $quoteExtensionDataFactory
     * @param QuoteExtensionData $quoteExtensionDataResource
     */
    public function __construct(
        Builder $quoteResultBuilder,
        LoadAndValidate $loadAndValidate,
        QuoteExtensionDataFactory $quoteExtensionDataFactory,
        QuoteExtensionData $quoteExtensionDataResource
    ) {
        $this->quoteResultBuilder = $quoteResultBuilder;
        $this->loadAndValidate = $loadAndValidate;
        $this->quoteExtensionDataFactory = $quoteExtensionDataFactory;
        $this->quoteExtensionDataResource = $quoteExtensionDataResource;
    }

    /**
     * Prevent setting addresses if order is already created.
     *
     * @param SetQuoteAddresses $subject
     * @param callable $proceed
     * @param string $shopId
     * @param int $cartId
     * @param AddressInterface $billingAddress
     * @param AddressInterface $shippingAddress
     * @return ResultInterface
     */
    public function aroundSetAddresses(
        SetQuoteAddresses $subject,
        callable $proceed,
        string $shopId,
        int $cartId,
        AddressInterface $billingAddress,
        AddressInterface $shippingAddress
    ): ResultInterface {
        try {
            $quote = $this->loadAndValidate->load($shopId, $cartId);
        } catch (LocalizedException $e) {
            return $this->quoteResultBuilder->createErrorResult($e->getMessage());
        }
        $quoteExtensionData = $this->quoteExtensionDataFactory->create();
        $this->quoteExtensionDataResource->load(
            $quoteExtensionData,
            $quote->getId(), QuoteExtensionData::QUOTE_ID
        );
        if (!$quoteExtensionData->getOrderCreated()) {
            return $proceed($shopId, $cartId, $billingAddress, $shippingAddress);
        }
        $quote->collectTotals();
        return $this->quoteResultBuilder->createSuccessResult($quote);
    }
}
