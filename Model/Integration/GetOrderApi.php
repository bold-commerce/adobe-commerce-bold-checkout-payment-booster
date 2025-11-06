<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Integration;

use Bold\CheckoutPaymentBooster\Api\Data\Integration\GetOrderResponseInterface;
use Bold\CheckoutPaymentBooster\Api\Data\Integration\GetOrderResponseInterfaceFactory;
use Bold\CheckoutPaymentBooster\Api\Data\Integration\OrderDataInterfaceFactory;
use Bold\CheckoutPaymentBooster\Api\Integration\GetOrderApiInterface;
use Bold\CheckoutPaymentBooster\Model\Http\SharedSecretAuthorization;
use Bold\CheckoutPaymentBooster\Model\Order\CheckPaymentMethod;
use Bold\CheckoutPaymentBooster\Model\ResourceModel\GetWebsiteIdByShopId;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

class GetOrderApi implements GetOrderApiInterface
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
     * @var GetOrderResponseInterfaceFactory
     */
    private $responseFactory;

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
     * @param GetOrderResponseInterfaceFactory $responseFactory
     * @param SharedSecretAuthorization $sharedSecretAuthorization
     * @param GetWebsiteIdByShopId $getWebsiteIdByShopId
     * @param OrderDataInterfaceFactory $orderDataFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param CheckPaymentMethod $checkPaymentMethod
     */
    public function __construct(
        GetOrderResponseInterfaceFactory $responseFactory,
        SharedSecretAuthorization $sharedSecretAuthorization,
        GetWebsiteIdByShopId $getWebsiteIdByShopId,
        OrderDataInterfaceFactory $orderDataFactory,
        OrderRepositoryInterface $orderRepository,
        CheckPaymentMethod $checkPaymentMethod
    ) {
        $this->responseFactory = $responseFactory;
        $this->sharedSecretAuthorization = $sharedSecretAuthorization;
        $this->getWebsiteIdByShopId = $getWebsiteIdByShopId;
        $this->orderDataFactory = $orderDataFactory;
        $this->orderRepository = $orderRepository;
        $this->checkPaymentMethod = $checkPaymentMethod;
    }

    /**
     * @inheritDoc
     */
    public function getOrder(
        string $shopId,
        string $platformOrderId
    ): GetOrderResponseInterface {
        $websiteId = $this->getWebsiteIdByShopId->getWebsiteId($shopId);
        $result = $this->responseFactory->create();

        if (!$this->sharedSecretAuthorization->isAuthorized($websiteId, true)) {
            return $result
                ->setResponseHttpStatus(401)
                ->addErrorWithMessage(__('The consumer isn\'t authorized to access resource.')->getText());
        }

        try {
            // Load order by platform_order_id (entity_id)
            /** @var OrderInterface $order */
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
        } catch (\Exception $e) {
            return $result
                ->setResponseHttpStatus(500)
                ->addErrorWithMessage($e->getMessage());
        }
    }
}

