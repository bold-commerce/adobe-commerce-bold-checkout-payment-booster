<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Integration;

use Bold\CheckoutPaymentBooster\Api\Data\Integration\UpdateQuoteItemsResponseInterface;
use Bold\CheckoutPaymentBooster\Api\Data\Integration\UpdateQuoteItemsResponseInterfaceFactory;
use Bold\CheckoutPaymentBooster\Api\Data\Integration\QuoteDataInterfaceFactory;
use Bold\CheckoutPaymentBooster\Api\Integration\UpdateQuoteItemsApiInterface;
use Bold\CheckoutPaymentBooster\Model\Http\SharedSecretAuthorization;
use Bold\CheckoutPaymentBooster\Model\ResourceModel\GetWebsiteIdByShopId;
use Bold\CheckoutPaymentBooster\Service\Integration\MagentoQuote\Update;
use Bold\CheckoutPaymentBooster\Service\Integration\MagentoQuote\Items;
use Magento\Framework\App\Request\Http as Request;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;

class UpdateQuoteItemsApi implements UpdateQuoteItemsApiInterface
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
     * @var UpdateQuoteItemsResponseInterfaceFactory
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
     * @var Items
     */
    private $quoteItemsService;

    /**
     * @var Request
     */
    private $request;

    /**
     * @param UpdateQuoteItemsResponseInterfaceFactory $responseFactory
     * @param SharedSecretAuthorization $sharedSecretAuthorization
     * @param GetWebsiteIdByShopId $getWebsiteIdByShopId
     * @param QuoteDataInterfaceFactory $quoteDataFactory
     * @param Update $quoteUpdateService
     * @param Items $quoteItemsService
     * @param Request $request
     */
    public function __construct(
        UpdateQuoteItemsResponseInterfaceFactory $responseFactory,
        SharedSecretAuthorization $sharedSecretAuthorization,
        GetWebsiteIdByShopId $getWebsiteIdByShopId,
        QuoteDataInterfaceFactory $quoteDataFactory,
        Update $quoteUpdateService,
        Items $quoteItemsService,
        Request $request
    ) {
        $this->responseFactory = $responseFactory;
        $this->sharedSecretAuthorization = $sharedSecretAuthorization;
        $this->getWebsiteIdByShopId = $getWebsiteIdByShopId;
        $this->quoteDataFactory = $quoteDataFactory;
        $this->quoteUpdateService = $quoteUpdateService;
        $this->quoteItemsService = $quoteItemsService;
        $this->request = $request;
    }

    /**
     * @inheritDoc
     */
    public function updateItems(
        string $shopId,
        string $quoteMaskId
    ): UpdateQuoteItemsResponseInterface {
        $websiteId = $this->getWebsiteIdByShopId->getWebsiteId($shopId);
        $result = $this->responseFactory->create();

        // Authorize request
        if (!$this->sharedSecretAuthorization->isAuthorized($websiteId, true)) {
            return $result
                ->setResponseHttpStatus(401)
                ->addErrorWithMessage(__('The consumer isn\'t authorized to access resource.')->getText());
        }

        // Parse request body
        $params = json_decode($this->request->getContent(), true);
        if (!$params) {
            return $result
                ->setResponseHttpStatus(422)
                ->addErrorWithMessage(__('Invalid request body.')->getText());
        }

        // Validate items array exists
        if (!isset($params['items']) || !is_array($params['items']) || empty($params['items'])) {
            return $result
                ->setResponseHttpStatus(422)
                ->addErrorWithMessage(__('The key items is required and must be a non-empty array.')->getText());
        }

        try {
            /** @var CartInterface $quote */
            $quote = $this->quoteUpdateService->loadQuoteByMaskId($quoteMaskId);

            // Validate this is an integration cart
            /** @var Quote $quote */
            $isBoldIntegrationCart = $quote->getExtensionAttributes()->getIsBoldIntegrationCart();
            if (!$isBoldIntegrationCart) {
                return $result
                    ->setResponseHttpStatus(422)
                    ->addErrorWithMessage(__('This endpoint can only be used for integration quotes.')->getText());
            }

            // Get store ID for product lookup
            $storeId = (int)$quote->getStoreId();

            // Update item quantities in quote
            $quote = $this->quoteItemsService->updateQuoteItemQuantities($quote, $params['items'], $storeId);

            // Save the quote with updates
            $quote = $this->quoteUpdateService->saveQuote($quote);

            // Get updated totals and shipping methods
            $quoteTotals = $this->quoteUpdateService->getQuoteTotals($quote);
            $shippingMethods = $this->quoteUpdateService->getAvailableShippingMethods($quote);
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

        $quoteDataObject = $this->quoteDataFactory->create();
        $quoteDataObject->setQuoteMaskId($quoteMaskId);
        $quoteDataObject->setQuote($quote);
        $quoteDataObject->setTotals($quoteTotals);
        $quoteDataObject->setShippingMethods($shippingMethods);

        return $result->setResponseHttpStatus(200)->setData($quoteDataObject);
    }
}

