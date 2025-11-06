<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Integration;

use Bold\CheckoutPaymentBooster\Api\Data\Integration\GetQuoteResponseInterface;
use Bold\CheckoutPaymentBooster\Api\Data\Integration\GetQuoteResponseInterfaceFactory;
use Bold\CheckoutPaymentBooster\Api\Data\Integration\QuoteDataInterfaceFactory;
use Bold\CheckoutPaymentBooster\Api\Integration\GetQuoteApiInterface;
use Bold\CheckoutPaymentBooster\Model\Http\SharedSecretAuthorization;
use Bold\CheckoutPaymentBooster\Model\ResourceModel\GetWebsiteIdByShopId;
use Bold\CheckoutPaymentBooster\Service\Integration\MagentoQuote\Update;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\Data\CartInterface;

class GetQuoteApi implements GetQuoteApiInterface
{
    /**
     * @var SharedSecretAuthorization
     */
    private $sharedSecretAuthorization;

    /**
     * @var GetWebsiteIdByShopId
     */
    private $getWebsiteIdByShopId;

    /**
     * @var GetQuoteResponseInterfaceFactory
     */
    private $responseFactory;

    /**
     * @var QuoteDataInterfaceFactory
     */
    private $quoteDataFactory;

    /**
     * @var Update
     */
    private $quoteUpdateService;

    /**
     * @param GetQuoteResponseInterfaceFactory $responseFactory
     * @param SharedSecretAuthorization $sharedSecretAuthorization
     * @param GetWebsiteIdByShopId $getWebsiteIdByShopId
     * @param QuoteDataInterfaceFactory $quoteDataFactory
     * @param Update $quoteUpdateService
     */
    public function __construct(
        GetQuoteResponseInterfaceFactory $responseFactory,
        SharedSecretAuthorization $sharedSecretAuthorization,
        GetWebsiteIdByShopId $getWebsiteIdByShopId,
        QuoteDataInterfaceFactory $quoteDataFactory,
        Update $quoteUpdateService
    ) {
        $this->responseFactory = $responseFactory;
        $this->sharedSecretAuthorization = $sharedSecretAuthorization;
        $this->getWebsiteIdByShopId = $getWebsiteIdByShopId;
        $this->quoteDataFactory = $quoteDataFactory;
        $this->quoteUpdateService = $quoteUpdateService;
    }

    /**
     * @inheritDoc
     */
    public function getQuote(
        string $shopId,
        string $quoteMaskId
    ): GetQuoteResponseInterface {
        $websiteId = $this->getWebsiteIdByShopId->getWebsiteId($shopId);
        $result = $this->responseFactory->create();

        if (!$this->sharedSecretAuthorization->isAuthorized($websiteId, true)) {
            return $result
                ->setResponseHttpStatus(401)
                ->addErrorWithMessage(__('The consumer isn\'t authorized to access resource.')->getText());
        }

        try {
            // Load quote by masked ID
            /** @var CartInterface $quote */
            $quote = $this->quoteUpdateService->loadQuoteByMaskId($quoteMaskId);
            
            // Recalculate totals to ensure data is fresh
            $quote = $this->quoteUpdateService->saveQuote($quote);
            
            // Get quote totals
            $quoteTotals = $this->quoteUpdateService->getQuoteTotals($quote);
            
            // Get available shipping methods
            $shippingMethods = $this->quoteUpdateService->getAvailableShippingMethods($quote);
            
            // Build response
            $quoteDataObject = $this->quoteDataFactory->create();
            $quoteDataObject->setQuoteMaskId($quoteMaskId);
            $quoteDataObject->setQuote($quote);
            $quoteDataObject->setTotals($quoteTotals);
            $quoteDataObject->setShippingMethods($shippingMethods);

            return $result->setResponseHttpStatus(200)->setData($quoteDataObject);
        } catch (NoSuchEntityException $e) {
            return $result
                ->setResponseHttpStatus(404)
                ->addErrorWithMessage($e->getMessage());
        } catch (LocalizedException $e) {
            return $result
                ->setResponseHttpStatus(422)
                ->addErrorWithMessage($e->getMessage());
        } catch (\Exception $e) {
            return $result
                ->setResponseHttpStatus(500)
                ->addErrorWithMessage($e->getMessage());
        }
    }
}

