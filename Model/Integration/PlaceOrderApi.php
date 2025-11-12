<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Integration;

use Bold\CheckoutPaymentBooster\Api\Data\Integration\OrderDataInterfaceFactory;
use Bold\CheckoutPaymentBooster\Api\Data\Integration\PlaceOrderResponseInterface;
use Bold\CheckoutPaymentBooster\Api\Data\Integration\PlaceOrderResponseInterfaceFactory;
use Bold\CheckoutPaymentBooster\Api\Integration\PlaceOrderApiInterface;
use Bold\CheckoutPaymentBooster\Model\Http\SharedSecretAuthorization;
use Bold\CheckoutPaymentBooster\Model\Payment\Gateway\Service;
use Bold\CheckoutPaymentBooster\Model\ResourceModel\GetWebsiteIdByShopId;
use Bold\CheckoutPaymentBooster\Service\Integration\MagentoOrder\Payment as MagentoOrderPaymentService;
use Magento\Framework\App\Request\Http as Request;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\PaymentInterfaceFactory;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMask as QuoteIdMaskResource;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;

class PlaceOrderApi implements PlaceOrderApiInterface
{
    /**
     * @var PlaceOrderResponseInterfaceFactory
     */
    private $responseFactory;

    /**
     * @var SharedSecretAuthorization
     */
    private $sharedSecretAuthorization;

    /**
     * @var GetWebsiteIdByShopId
     */
    private $getWebsiteIdByShopId;

    /**
     * @var OrderDataInterfaceFactory
     */
    private $orderDataFactory;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

    /**
     * @var QuoteIdMaskResource
     */
    private $quoteIdMaskResource;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var CartManagementInterface
     */
    private $cartManagement;

    /**
     * @var PaymentInterfaceFactory
     */
    private $paymentFactory;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var MagentoOrderPaymentService
     */
    private $magentoOrderPaymentService;

    /**
     * @param PlaceOrderResponseInterfaceFactory $responseFactory
     * @param SharedSecretAuthorization $sharedSecretAuthorization
     * @param GetWebsiteIdByShopId $getWebsiteIdByShopId
     * @param OrderDataInterfaceFactory $orderDataFactory
     * @param Request $request
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param QuoteIdMaskResource $quoteIdMaskResource
     * @param CartRepositoryInterface $cartRepository
     * @param CartManagementInterface $cartManagement
     * @param PaymentInterfaceFactory $paymentFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param LoggerInterface $logger
     * @param MagentoOrderPaymentService $magentoOrderPaymentService
     */
    public function __construct(
        PlaceOrderResponseInterfaceFactory $responseFactory,
        SharedSecretAuthorization $sharedSecretAuthorization,
        GetWebsiteIdByShopId $getWebsiteIdByShopId,
        OrderDataInterfaceFactory $orderDataFactory,
        Request $request,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        QuoteIdMaskResource $quoteIdMaskResource,
        CartRepositoryInterface $cartRepository,
        CartManagementInterface $cartManagement,
        PaymentInterfaceFactory $paymentFactory,
        OrderRepositoryInterface $orderRepository,
        LoggerInterface $logger,
        MagentoOrderPaymentService $magentoOrderPaymentService
    ) {
        $this->responseFactory = $responseFactory;
        $this->sharedSecretAuthorization = $sharedSecretAuthorization;
        $this->getWebsiteIdByShopId = $getWebsiteIdByShopId;
        $this->orderDataFactory = $orderDataFactory;
        $this->request = $request;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->quoteIdMaskResource = $quoteIdMaskResource;
        $this->cartRepository = $cartRepository;
        $this->cartManagement = $cartManagement;
        $this->paymentFactory = $paymentFactory;
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
        $this->magentoOrderPaymentService = $magentoOrderPaymentService;
    }

    /**
     * @inheritDoc
     */
    public function placeOrder(
        string $shopId,
        string $quoteMaskId
    ): PlaceOrderResponseInterface {
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

        try {
            // Load quote by mask ID
            /** @var QuoteIdMask $quoteIdMask */
            $quoteIdMask = $this->quoteIdMaskFactory->create();
            $this->quoteIdMaskResource->load($quoteIdMask, $quoteMaskId, 'masked_id');
            
            if (!$quoteIdMask->getQuoteId()) {
                return $result
                    ->setResponseHttpStatus(404)
                    ->addErrorWithMessage(__('No quote found with mask ID "%1"', $quoteMaskId)->render());
            }

            /** @var Quote $quote */
            $quote = $this->cartRepository->get($quoteIdMask->getQuoteId());

            if (!$quote->getIsActive()) {
                return $result
                    ->setResponseHttpStatus(422)
                    ->addErrorWithMessage(__('Quote with ID "%1" is not active', $quoteMaskId)->render());
            }

            // Validate this is an integration cart
            $isBoldIntegrationCart = $quote->getExtensionAttributes()->getIsBoldIntegrationCart();
            if (!$isBoldIntegrationCart) {
                return $result
                    ->setResponseHttpStatus(422)
                    ->addErrorWithMessage(__('This endpoint can only be used for integration quotes.')->getText());
            }

            // Validate transaction data exists and has at least one authorized transaction
            $this->validateTransactionData($params);

            // Set Bold payment method
            $payment = $this->paymentFactory->create();
            $payment->setMethod(Service::CODE);
            $quote->setPayment($payment);
            $quote->getPayment()->importData(['method' => Service::CODE]);
            $this->cartRepository->save($quote);

            // Place the order
            $orderId = $this->cartManagement->placeOrder($quote->getId());

            // Load the order
            $order = $this->orderRepository->get($orderId);

            // Save transaction data to order payment using MagentoOrder payment service
            $this->magentoOrderPaymentService->saveTransactionData($order, $params);

            // Build response
            $orderData = $this->orderDataFactory->create();
            $orderData->setPlatformOrderId((string)$order->getEntityId());
            $orderData->setPlatformFriendlyId((string)$order->getIncrementId());
            $orderData->setOrder($order);

            return $result->setResponseHttpStatus(200)->setData($orderData);
        } catch (LocalizedException $e) {
            $this->logger->critical(
                'Placing integration order failed (reason: ' . $e->getMessage() . ')',
                [
                    'quote_mask_id' => $quoteMaskId,
                    'exception' => (string)$e,
                ]
            );
            return $result
                ->setResponseHttpStatus(422)
                ->addErrorWithMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->logger->critical($e);
            return $result
                ->setResponseHttpStatus(500)
                ->addErrorWithMessage(__('An error occurred on the server. Please try to place the order again.')->getText());
        }
    }

    /**
     * Validate that transaction data contains at least one authorized payment.
     *
     * @param array<string, mixed> $params Request parameters with transaction data
     * @return void
     * @throws LocalizedException
     */
    private function validateTransactionData(array $params): void
    {
        $transactions = $params['transactions'] ?? [];

        if (empty($transactions)) {
            throw new LocalizedException(
                __(
                    'Cannot place order without payment authorization. '
                    . 'At least one authorized transaction is required.'
                )
            );
        }

        // Check that at least one transaction has a valid transaction_id
        $hasValidTransaction = false;
        foreach ($transactions as $transaction) {
            if (!empty($transaction['transaction_id'])) {
                $hasValidTransaction = true;
                break;
            }
        }

        if (!$hasValidTransaction) {
            throw new LocalizedException(
                __(
                    'Cannot place order without valid transaction ID. '
                    . 'At least one transaction must have a transaction_id.'
                )
            );
        }
    }

}

