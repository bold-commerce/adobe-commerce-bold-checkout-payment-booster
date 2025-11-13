<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Integration;

use Bold\CheckoutPaymentBooster\Api\Data\Integration\OrderDataInterfaceFactory;
use Bold\CheckoutPaymentBooster\Api\Data\Integration\UpdateOrderPaymentsResponseInterface;
use Bold\CheckoutPaymentBooster\Api\Data\Integration\UpdateOrderPaymentsResponseInterfaceFactory;
use Bold\CheckoutPaymentBooster\Api\Integration\UpdateOrderPaymentsApiInterface;
use Bold\CheckoutPaymentBooster\Model\Http\SharedSecretAuthorization;
use Bold\CheckoutPaymentBooster\Model\Order\CheckPaymentMethod;
use Bold\CheckoutPaymentBooster\Model\ResourceModel\GetWebsiteIdByShopId;
use Bold\CheckoutPaymentBooster\Service\Integration\MagentoOrder\Payment as MagentoOrderPaymentService;
use Magento\Framework\App\Request\Http as Request;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

class UpdateOrderPaymentsApi implements UpdateOrderPaymentsApiInterface
{
    /**
     * @var UpdateOrderPaymentsResponseInterfaceFactory
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
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var CheckPaymentMethod
     */
    private $checkPaymentMethod;

    /**
     * @var MagentoOrderPaymentService
     */
    private $magentoOrderPaymentService;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param UpdateOrderPaymentsResponseInterfaceFactory $responseFactory
     * @param SharedSecretAuthorization $sharedSecretAuthorization
     * @param GetWebsiteIdByShopId $getWebsiteIdByShopId
     * @param OrderDataInterfaceFactory $orderDataFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param CheckPaymentMethod $checkPaymentMethod
     * @param MagentoOrderPaymentService $magentoOrderPaymentService
     * @param Request $request
     * @param LoggerInterface $logger
     */
    public function __construct(
        UpdateOrderPaymentsResponseInterfaceFactory $responseFactory,
        SharedSecretAuthorization $sharedSecretAuthorization,
        GetWebsiteIdByShopId $getWebsiteIdByShopId,
        OrderDataInterfaceFactory $orderDataFactory,
        OrderRepositoryInterface $orderRepository,
        CheckPaymentMethod $checkPaymentMethod,
        MagentoOrderPaymentService $magentoOrderPaymentService,
        Request $request,
        LoggerInterface $logger
    ) {
        $this->responseFactory = $responseFactory;
        $this->sharedSecretAuthorization = $sharedSecretAuthorization;
        $this->getWebsiteIdByShopId = $getWebsiteIdByShopId;
        $this->orderDataFactory = $orderDataFactory;
        $this->orderRepository = $orderRepository;
        $this->checkPaymentMethod = $checkPaymentMethod;
        $this->magentoOrderPaymentService = $magentoOrderPaymentService;
        $this->request = $request;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function updatePayments(
        string $shopId,
        string $platformOrderId
    ): UpdateOrderPaymentsResponseInterface {
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

        // Validate financial_status is provided
        if (!isset($params['financial_status']) || empty($params['financial_status'])) {
            return $result
                ->setResponseHttpStatus(422)
                ->addErrorWithMessage(__('The key financial_status is required.')->getText());
        }

        // Validate transactions array based on financial_status
        $financialStatus = $params['financial_status'];
        if (!isset($params['transactions']) || !is_array($params['transactions'])) {
            return $result
                ->setResponseHttpStatus(422)
                ->addErrorWithMessage(__('The key transactions is required and must be an array.')->getText());
        }

        // For non-cancelled statuses, transactions array must have at least one transaction
        if ($financialStatus !== 'cancelled' && empty($params['transactions'])) {
            return $result
                ->setResponseHttpStatus(422)
                ->addErrorWithMessage(__('The key transactions must contain at least one transaction for this financial status.')->getText());
        }

        try {
            // Load order by platform_order_id (entity_id)
            /** @var OrderInterface&Order $order */
            $order = $this->orderRepository->get((int)$platformOrderId);

            // Validate order uses Bold payment method
            if (!$this->checkPaymentMethod->isBold($order)) {
                return $result
                    ->setResponseHttpStatus(422)
                    ->addErrorWithMessage(
                        __(
                            'Order with ID "%1" does not use a Bold payment method. ' .
                            'This endpoint can only be used for orders with Bold payment methods.',
                            $platformOrderId
                        )->render()
                    );
            }

            // Update payment using MagentoOrder payment service
            $this->magentoOrderPaymentService->updatePayment(
                $order,
                $params['financial_status'],
                $params['transactions']
            );

            // Reload order to get updated data
            $order = $this->orderRepository->get((int)$platformOrderId);

            // Build response with order data
            $orderData = $this->orderDataFactory->create();
            $orderData->setPlatformOrderId((string)$order->getEntityId());
            $orderData->setPlatformFriendlyId((string)$order->getIncrementId());
            $orderData->setOrder($order);

            return $result->setResponseHttpStatus(200)->setData($orderData);
        } catch (NoSuchEntityException $e) {
            return $result
                ->setResponseHttpStatus(404)
                ->addErrorWithMessage(
                    __('Order with ID "%1" does not exist.', $platformOrderId)->render()
                );
        } catch (LocalizedException $e) {
            $this->logger->critical(
                'Updating integration order payment failed (reason: ' . $e->getMessage() . ')',
                [
                    'platform_order_id' => $platformOrderId,
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
                ->addErrorWithMessage(__('An unexpected error occurred while processing the request.')->getText());
        }
    }
}

