<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Integration;

use Bold\CheckoutPaymentBooster\Api\Data\Integration\CreateQuoteResponseInterface;
use Bold\CheckoutPaymentBooster\Api\Data\Integration\CreateQuoteResponseInterfaceFactory;
use Bold\CheckoutPaymentBooster\Api\Data\Integration\QuoteDataInterfaceFactory;
use Bold\CheckoutPaymentBooster\Api\Integration\CreateQuoteApiInterface;
use Bold\CheckoutPaymentBooster\Model\Http\SharedSecretAuthorization;
use Bold\CheckoutPaymentBooster\Model\ResourceModel\GetWebsiteIdByShopId;
use Bold\CheckoutPaymentBooster\Service\Integration\MagentoQuote\Create;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\Request\Http as Request;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\Website;

class CreateQuoteApi implements CreateQuoteApiInterface
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
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var CreateQuoteResponseInterfaceFactory
     */
    private $responseFactory;

    /**
     * @var QuoteDataInterfaceFactory
     */
    private $quoteDataFactory;

    /** @var Create */
    private $quoteCreateService;

    /** @var ProductRepositoryInterface */
    private $productRepository;

    /** @var Request */
    private $request;

    /**
     * @param CreateQuoteResponseInterfaceFactory $responseFactory
     * @param SharedSecretAuthorization $sharedSecretAuthorization
     * @param GetWebsiteIdByShopId $getWebsiteIdByShopId
     * @param StoreManagerInterface $storeManager
     * @param QuoteDataInterfaceFactory $quoteDataFactory
     * @param Create $quoteCreateService
     * @param ProductRepositoryInterface $productRepository
     * @param Request $request
     */
    public function __construct(
        CreateQuoteResponseInterfaceFactory $responseFactory,
        SharedSecretAuthorization $sharedSecretAuthorization,
        GetWebsiteIdByShopId $getWebsiteIdByShopId,
        StoreManagerInterface $storeManager,
        QuoteDataInterfaceFactory $quoteDataFactory,
        Create $quoteCreateService,
        ProductRepositoryInterface $productRepository,
        Request  $request
    ) {
        $this->responseFactory = $responseFactory;
        $this->sharedSecretAuthorization = $sharedSecretAuthorization;
        $this->getWebsiteIdByShopId = $getWebsiteIdByShopId;
        $this->storeManager = $storeManager;
        $this->quoteDataFactory = $quoteDataFactory;
        $this->quoteCreateService = $quoteCreateService;
        $this->productRepository = $productRepository;
        $this->request = $request;
    }

    /**
     * @inheritDoc
     */
    public function createQuote(
        string $shopId
    ): CreateQuoteResponseInterface {
        $websiteId = $this->getWebsiteIdByShopId->getWebsiteId($shopId);
        $result = $this->responseFactory->create();

        if (!$this->sharedSecretAuthorization->isAuthorized($websiteId, true)) {
            return $result
                ->setResponseHttpStatus(401)
                ->addErrorWithMessage(__('The consumer isn\'t authorized to access resource.')->getText());
        }

        $params = json_decode($this->request->getContent(), true);

        if (isset($params['public_order_id'])) {
            $publicOrderId = $params['public_order_id'];
        } else {
            return $result
                ->setResponseHttpStatus(422)
                ->addErrorWithMessage(__('The key public_order_id is required.')->getText());
        }

        if (isset($params['items'])) {
            $requestItems = $params['items'];
        } else {
            return $result
                ->setResponseHttpStatus(422)
                ->addErrorWithMessage(__('The key items is required.')->getText());
        }

        try {
            /** @var Website $website */
            $website = $this->storeManager->getWebsite($websiteId);
            $store = $website->getDefaultStore();
            $storeId = (int)$store->getId();
            /** @var Quote $quote */
            $quote = $this->quoteCreateService->createQuote($storeId, $publicOrderId);

            if ($requestItems) {
                foreach ($requestItems as $requestItem) {
                    if (isset($requestItem['product_id'])) {
                        /** @var Product $product */
                        $product = $this->productRepository->getById($requestItem['product_id'], false, $storeId);
                    } else if (isset($requestItem['sku'])) {
                        /** @var Product $product */
                        $product = $this->productRepository->get($requestItem['sku'], false, $storeId);
                    } else {
                        return $result
                            ->setResponseHttpStatus(422)
                            ->addErrorWithMessage(__('Each item must include either sku or product_id.')->getText());
                    }
                    if ($product) {
                        $quote->addProduct($product, $requestItem['quantity']);
                    }
                }

                $quote = $this->quoteCreateService->saveQuote($quote);
            }
            $quoteMaskId = $this->quoteCreateService->createQuoteIdMask($quote);
            $quoteTotals = $this->quoteCreateService->getQuoteTotals($quote);
        } catch (\Exception $e) {
            return $result
                ->setResponseHttpStatus(422)
                ->addErrorWithMessage($e->getMessage());
        }

        $quoteDataObject = $this->quoteDataFactory->create();
        $quoteDataObject->setQuoteMaskId($quoteMaskId->getMaskedId());
        $quoteDataObject->setQuote($quote);
        $quoteDataObject->setTotals($quoteTotals);

        return $result->setResponseHttpStatus(200)->setData($quoteDataObject);
    }
}
