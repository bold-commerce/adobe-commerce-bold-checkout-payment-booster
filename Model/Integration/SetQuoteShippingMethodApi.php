<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Integration;

use Bold\CheckoutPaymentBooster\Api\Data\Integration\SetQuoteShippingMethodResponseInterface;
use Bold\CheckoutPaymentBooster\Api\Data\Integration\SetQuoteShippingMethodResponseInterfaceFactory;
use Bold\CheckoutPaymentBooster\Api\Data\Integration\QuoteDataInterfaceFactory;
use Bold\CheckoutPaymentBooster\Api\Integration\SetQuoteShippingMethodApiInterface;
use Bold\CheckoutPaymentBooster\Model\Http\SharedSecretAuthorization;
use Bold\CheckoutPaymentBooster\Model\ResourceModel\GetWebsiteIdByShopId;
use Bold\CheckoutPaymentBooster\Service\Integration\MagentoQuote\Update;
use Magento\Framework\App\Request\Http as Request;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;

class SetQuoteShippingMethodApi implements SetQuoteShippingMethodApiInterface
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
     * @var SetQuoteShippingMethodResponseInterfaceFactory
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
     * @param SetQuoteShippingMethodResponseInterfaceFactory $responseFactory
     * @param SharedSecretAuthorization $sharedSecretAuthorization
     * @param GetWebsiteIdByShopId $getWebsiteIdByShopId
     * @param QuoteDataInterfaceFactory $quoteDataFactory
     * @param Update $quoteUpdateService
     * @param Request $request
     */
    public function __construct(
        SetQuoteShippingMethodResponseInterfaceFactory $responseFactory,
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
    public function setShippingMethod(
        string $shopId,
        string $quoteMaskId
    ): SetQuoteShippingMethodResponseInterface {
        $websiteId = $this->getWebsiteIdByShopId->getWebsiteId($shopId);
        $result = $this->responseFactory->create();

        if (!$this->sharedSecretAuthorization->isAuthorized($websiteId, true)) {
            return $result
                ->setResponseHttpStatus(401)
                ->addErrorWithMessage(__('The consumer isn\'t authorized to access resource.')->getText());
        }

        $params = json_decode($this->request->getContent(), true);

        // Validate that required fields are provided
        if (empty($params['carrier_code'])) {
            return $result
                ->setResponseHttpStatus(422)
                ->addErrorWithMessage(__('The key carrier_code is required.')->getText());
        }

        if (empty($params['method_code'])) {
            return $result
                ->setResponseHttpStatus(422)
                ->addErrorWithMessage(__('The key method_code is required.')->getText());
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

            // Set the shipping method
            $quote = $this->quoteUpdateService->setShippingMethod(
                $quote,
                $params['carrier_code'],
                $params['method_code']
            );

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

