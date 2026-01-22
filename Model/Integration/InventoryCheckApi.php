<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Integration;

use Bold\CheckoutPaymentBooster\Api\Data\Integration\InventoryCheckDataInterfaceFactory;
use Bold\CheckoutPaymentBooster\Api\Data\Integration\InventoryCheckResponseInterface;
use Bold\CheckoutPaymentBooster\Api\Data\Integration\InventoryCheckResponseInterfaceFactory;
use Bold\CheckoutPaymentBooster\Api\Integration\InventoryCheckApiInterface;
use Bold\CheckoutPaymentBooster\Model\Http\SharedSecretAuthorization;
use Bold\CheckoutPaymentBooster\Model\Integration\InventoryChecker\InventoryCheckerFactory;
use Bold\CheckoutPaymentBooster\Model\ResourceModel\GetWebsiteIdByShopId;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\Request\Http as Request;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Inventory Check API implementation with dual-path support for MSI and legacy inventory systems.
 */
class InventoryCheckApi implements InventoryCheckApiInterface
{
    /**
     * @var InventoryCheckResponseInterfaceFactory
     */
    private $responseFactory;

    /**
     * @var InventoryCheckDataInterfaceFactory
     */
    private $dataFactory;

    /**
     * @var SharedSecretAuthorization
     */
    private $sharedSecretAuthorization;

    /**
     * @var GetWebsiteIdByShopId
     */
    private $getWebsiteIdByShopId;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var InventoryCheckerFactory
     */
    private $inventoryCheckerFactory;

    /**
     * @param InventoryCheckResponseInterfaceFactory $responseFactory
     * @param InventoryCheckDataInterfaceFactory $dataFactory
     * @param SharedSecretAuthorization $sharedSecretAuthorization
     * @param GetWebsiteIdByShopId $getWebsiteIdByShopId
     * @param Request $request
     * @param StoreManagerInterface $storeManager
     * @param ProductRepositoryInterface $productRepository
     * @param LoggerInterface $logger
     * @param InventoryCheckerFactory $inventoryCheckerFactory
     */
    public function __construct(
        InventoryCheckResponseInterfaceFactory $responseFactory,
        InventoryCheckDataInterfaceFactory $dataFactory,
        SharedSecretAuthorization $sharedSecretAuthorization,
        GetWebsiteIdByShopId $getWebsiteIdByShopId,
        Request $request,
        StoreManagerInterface $storeManager,
        ProductRepositoryInterface $productRepository,
        LoggerInterface $logger,
        InventoryCheckerFactory $inventoryCheckerFactory
    ) {
        $this->responseFactory = $responseFactory;
        $this->dataFactory = $dataFactory;
        $this->sharedSecretAuthorization = $sharedSecretAuthorization;
        $this->getWebsiteIdByShopId = $getWebsiteIdByShopId;
        $this->request = $request;
        $this->storeManager = $storeManager;
        $this->productRepository = $productRepository;
        $this->logger = $logger;
        $this->inventoryCheckerFactory = $inventoryCheckerFactory;
    }

    /**
     * @inheritDoc
     */
    public function check(string $shopId): InventoryCheckResponseInterface
    {
        /** @var InventoryCheckResponseInterface $response */
        $response = $this->responseFactory->create();

        try {
            $websiteId = $this->getWebsiteIdByShopId->getWebsiteId($shopId);
        } catch (\Exception $e) {
            return $response
                ->setResponseHttpStatus(404)
                ->addErrorWithMessage('Shop not found: ' . $e->getMessage());
        }

        // Authenticate request
        if (!$this->sharedSecretAuthorization->isAuthorized($websiteId, true)) {
            return $response
                ->setResponseHttpStatus(401)
                ->addErrorWithMessage('The consumer isn\'t authorized to access resource.');
        }

        // Parse request body
        $requestData = json_decode($this->request->getContent(), true);

        // Validate request parameters
        if (!isset($requestData['items']) || !is_array($requestData['items']) || empty($requestData['items'])) {
            return $response
                ->setResponseHttpStatus(422)
                ->addErrorWithMessage('Missing or invalid "items" parameter');
        }

        try {
            // Get website
            /** @var \Magento\Store\Model\Website $website */
            $website = $this->storeManager->getWebsite($websiteId);
            $storeId = (int) $website->getDefaultStore()->getId();

            // Parse and load products upfront
            $productItems = [];
            foreach ($requestData['items'] as $item) {
                // Either product_id or sku is required (product_id takes precedence)
                if (!isset($item['product_id']) && !isset($item['sku'])) {
                    $response->addErrorWithMessage('Each item must include either "sku" or "product_id"');
                    continue;
                }

                if (!isset($item['quantity'])) {
                    $response->addErrorWithMessage('Each item must have "quantity"');
                    continue;
                }

                // Load product (product_id takes precedence over sku)
                try {
                    if (isset($item['product_id'])) {
                        $product = $this->productRepository->getById((int) $item['product_id'], false, $storeId);
                    } else {
                        $product = $this->productRepository->get((string) $item['sku'], false, $storeId);
                    }

                    $productItems[] = [
                        'product' => $product,
                        'quantity' => (float) $item['quantity'],
                    ];
                } catch (NoSuchEntityException $e) {
                    $identifier = isset($item['product_id']) ? "ID {$item['product_id']}" : "SKU {$item['sku']}";
                    $response->addErrorWithMessage("Product not found: {$identifier}");
                    continue;
                }
            }

            if (empty($productItems)) {
                return $response
                    ->setResponseHttpStatus(422)
                    ->addErrorWithMessage('No valid items provided');
            }

            // Get appropriate inventory checker and check inventory
            $inventoryChecker = $this->inventoryCheckerFactory->create();
            $results = $inventoryChecker->checkInventory($productItems, $website);

            // Build response data
            $allAvailable = true;
            foreach ($results as $result) {
                if (!$result->getIsAvailable()) {
                    $allAvailable = false;
                    break;
                }
            }

            $data = $this->dataFactory->create();
            $data->setResults($results);
            $data->setIsAvailable($allAvailable);

            $response->setResponseHttpStatus(200);
            $response->setData($data);
        } catch (NoSuchEntityException $e) {
            return $response
                ->setResponseHttpStatus(404)
                ->addErrorWithMessage('Website not found: ' . $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error('Inventory check failed: ' . $e->getMessage(), [
                'shop_id' => $shopId,
                'exception' => $e,
            ]);

            return $response
                ->setResponseHttpStatus(500)
                ->addErrorWithMessage('Internal server error: ' . $e->getMessage());
        }

        return $response;
    }
}
