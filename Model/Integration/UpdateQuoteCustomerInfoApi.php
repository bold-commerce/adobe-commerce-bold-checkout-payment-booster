<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Integration;

use Bold\CheckoutPaymentBooster\Api\Data\Integration\UpdateQuoteCustomerInfoResponseInterface;
use Bold\CheckoutPaymentBooster\Api\Data\Integration\UpdateQuoteCustomerInfoResponseInterfaceFactory;
use Bold\CheckoutPaymentBooster\Api\Data\Integration\QuoteDataInterfaceFactory;
use Bold\CheckoutPaymentBooster\Api\Integration\UpdateQuoteCustomerInfoApiInterface;
use Bold\CheckoutPaymentBooster\Model\Http\SharedSecretAuthorization;
use Bold\CheckoutPaymentBooster\Model\ResourceModel\GetWebsiteIdByShopId;
use Bold\CheckoutPaymentBooster\Service\Integration\MagentoQuote\Update;
use Magento\Framework\App\Request\Http as Request;
use Magento\Quote\Api\Data\CartInterface;

class UpdateQuoteCustomerInfoApi implements UpdateQuoteCustomerInfoApiInterface
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
     * @var UpdateQuoteCustomerInfoResponseInterfaceFactory
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
     * @var Request
     */
    private $request;

    /**
     * @param UpdateQuoteCustomerInfoResponseInterfaceFactory $responseFactory
     * @param SharedSecretAuthorization $sharedSecretAuthorization
     * @param GetWebsiteIdByShopId $getWebsiteIdByShopId
     * @param QuoteDataInterfaceFactory $quoteDataFactory
     * @param Update $quoteUpdateService
     * @param Request $request
     */
    public function __construct(
        UpdateQuoteCustomerInfoResponseInterfaceFactory $responseFactory,
        SharedSecretAuthorization $sharedSecretAuthorization,
        GetWebsiteIdByShopId $getWebsiteIdByShopId,
        QuoteDataInterfaceFactory $quoteDataFactory,
        Update $quoteUpdateService,
        Request $request
    ) {
        $this->responseFactory = $responseFactory;
        $this->sharedSecretAuthorization = $sharedSecretAuthorization;
        $this->getWebsiteIdByShopId = $getWebsiteIdByShopId;
        $this->quoteDataFactory = $quoteDataFactory;
        $this->quoteUpdateService = $quoteUpdateService;
        $this->request = $request;
    }

    /**
     * @inheritDoc
     */
    public function updateCustomerInfo(
        string $shopId,
        string $quoteMaskId
    ): UpdateQuoteCustomerInfoResponseInterface {
        $websiteId = $this->getWebsiteIdByShopId->getWebsiteId($shopId);
        $result = $this->responseFactory->create();

        if (!$this->sharedSecretAuthorization->isAuthorized($websiteId, true)) {
            return $result
                ->setResponseHttpStatus(401)
                ->addErrorWithMessage(__('The consumer isn\'t authorized to access resource.')->getText());
        }

        $params = json_decode($this->request->getContent(), true);

        // Validate that at least one of customer, billing, or shipping is provided
        if (empty($params['customer']) && empty($params['billing']) && empty($params['shipping'])) {
            return $result
                ->setResponseHttpStatus(422)
                ->addErrorWithMessage(__('At least one of customer, billing, or shipping must be provided.')->getText());
        }

        try {
            /** @var CartInterface $quote */
            $quote = $this->quoteUpdateService->loadQuoteByMaskId($quoteMaskId);

            // Update customer information if provided
            if (!empty($params['customer'])) {
                $quote = $this->quoteUpdateService->updateCustomerInfo($quote, $params['customer']);
            }

            // Update billing address if provided
            if (!empty($params['billing'])) {
                $quote = $this->quoteUpdateService->updateBillingAddress($quote, $params['billing']);
            }

            // Update shipping address if provided
            if (!empty($params['shipping'])) {
                $quote = $this->quoteUpdateService->updateShippingAddress($quote, $params['shipping']);
            }

            // Save the quote with updates
            $quote = $this->quoteUpdateService->saveQuote($quote);

            // Get updated totals and shipping methods
            $quoteTotals = $this->quoteUpdateService->getQuoteTotals($quote);
            $shippingMethods = $this->quoteUpdateService->getAvailableShippingMethods($quote);
        } catch (\Exception $e) {
            return $result
                ->setResponseHttpStatus(422)
                ->addErrorWithMessage($e->getMessage());
        }

        $quoteDataObject = $this->quoteDataFactory->create();
        $quoteDataObject->setQuoteMaskId($quoteMaskId);
        $quoteDataObject->setQuote($quote);
        $quoteDataObject->setTotals($quoteTotals);
        $quoteDataObject->setShippingMethods($shippingMethods);

        return $result->setResponseHttpStatus(200)->setData($quoteDataObject);
    }
}

