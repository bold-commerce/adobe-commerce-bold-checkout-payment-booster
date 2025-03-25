<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Controller\Digitalwallets\Quote;

use Bold\CheckoutPaymentBooster\Service\DigitalWallets\MagentoQuote\Creator;
use Bold\CheckoutPaymentBooster\Service\DigitalWallets\MagentoQuote\TotalsRetriever;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Magento\Store\Model\StoreManagerInterface;

use function __;
use function count;

class Create implements HttpPostActionInterface, CsrfAwareActionInterface
{
    /**
     * @var Validator
     */
    private $formKeyValidator;
    /**
     * @var RequestInterface
     */
    private $request;
    /**
     * @var ResultFactory
     */
    private $resultFactory;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;
    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;
    /**
     * @var Creator
     */
    private $quoteCreator;
    /**
     * @var TotalsRetriever
     */
    private $totalsRetriever;

    public function __construct(
        Validator $formKeyValidator,
        RequestInterface $request,
        ResultFactory $resultFactory,
        StoreManagerInterface $storeManager,
        ProductRepositoryInterface $productRepository,
        CartRepositoryInterface $quoteRepository,
        Creator $quoteCreator,
        TotalsRetriever $totalsRetriever
    ) {
        $this->formKeyValidator = $formKeyValidator;
        $this->request = $request;
        $this->resultFactory = $resultFactory;
        $this->storeManager = $storeManager;
        $this->productRepository = $productRepository;
        $this->quoteRepository = $quoteRepository;
        $this->quoteCreator = $quoteCreator;
        $this->totalsRetriever = $totalsRetriever;
    }

    public function execute(): ResultInterface
    {
        $requestParameters = $this->request->getParams();
        /** @var int|string|null $productId */
        $productId = $requestParameters['product'] ?? null;

        unset($requestParameters['form_key'], $requestParameters['product']);

        if ($productId === null) {
            return $this->createErrorResult(
                (string)__('Please provide a product identifier to create a quote for.'),
                400
            );
        }

        $storeId = $this->storeManager->getStore()->getId();

        try {
            $product = $this->productRepository->getById((int)$productId, false, $storeId);
        } catch (NoSuchEntityException $noSuchEntityException) {
            return $this->createErrorResult((string)__('Invalid product identifier "%1".', $productId), 400);
        }

        try {
            ['quote' => $quote, 'maskedId' => $maskedId] = $this->quoteCreator->createQuote(
                $storeId,
                $product,
                $requestParameters
            );
        } catch (LocalizedException $localizedException) {
            return $this->createErrorResult($localizedException->getMessage(), 400);
        }

        // Reloading the quote again to ensure that we have all available data
        try {
            $quoteId = (int)$quote->getId(); // @phpstan-ignore-line
            /** @var CartInterface&Quote $quote */
            $quote = $this->quoteRepository->get($quoteId);
        } catch (NoSuchEntityException $noSuchEntityException) {
            return $this->createErrorResult((string)__('Could not retrieve newly created quote.'), 500);
        }

        $quoteData = $this->getQuoteData($quote, $maskedId);
        $quoteItemData = $this->getQuoteItemData($quote);
        $totalsData = $this->totalsRetriever->retrieveTotals($quoteId);

        return $this->createSuccessResult($quoteData, $quoteItemData, $totalsData);
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        // @phpstan-ignore-next-line
        return !$request->isPost() || !$request->isXmlHttpRequest() || !$this->formKeyValidator->validate($request);
    }

    /**
     * @param mixed[] $data
     */
    private function createResult(array $data, int $responseCode): ResultInterface
    {
        /** @var Json $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        $result->setData($data);
        $result->setHttpResponseCode($responseCode);

        return $result;
    }

    private function createErrorResult(string $errorMessage, int $responseCode): ResultInterface
    {
        $data = [
            'success' => false,
            'error' => $errorMessage,
        ];

        return $this->createResult($data, $responseCode);
    }

    /**
     * @param mixed[] $quoteData
     * @param mixed[] $quoteItemData
     * @param mixed[] $totalsData
     */
    private function createSuccessResult(array $quoteData, array $quoteItemData, array $totalsData): ResultInterface
    {
        $data = [
            'success' => true,
            'quoteData' => $quoteData,
            'quoteItemData' => $quoteItemData,
            'totalsData' => $totalsData,
        ];

        return $this->createResult($data, 200);
    }

    /**
     * @param CartInterface $quote
     * @param string|null $maskedId
     * @return mixed[]
     * @see \Magento\Checkout\Model\DefaultConfigProvider::getQuoteData
     */
    private function getQuoteData(CartInterface $quote, ?string $maskedId): array
    {
        /** @var mixed[] $quoteData */
        $quoteData = $quote->toArray(); // @phpstan-ignore-line
        $quoteData['is_virtual'] = $quote->getIsVirtual();

        if ($maskedId !== null) {
            $quoteData['entity_id'] = $maskedId;
        }

        if ($quote->getExtensionAttributes() !== null) {
            // @phpstan-ignore-next-line
            $quoteData['extension_attributes'] = $quote->getExtensionAttributes()->__toArray();
        }

        return $quoteData;
    }

    /**
     * @param CartInterface $quote
     * @return mixed[]
     */
    private function getQuoteItemData(CartInterface $quote): array
    {
        $quoteItems = $quote->getAllVisibleItems(); // @phpstan-ignore-line

        if (count($quoteItems) === 0) {
            return [];
        }

        return array_map(
            static function (Item $item): array {
                return (array)$item->getData();
            },
            $quoteItems
        );
    }
}
