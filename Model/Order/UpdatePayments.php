<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Order;

use Bold\CheckoutPaymentBooster\Api\Data\Order\Payment\ResultInterface;
use Bold\CheckoutPaymentBooster\Api\Data\Order\Payment\ResultInterfaceFactory;
use Bold\CheckoutPaymentBooster\Api\Order\UpdatePaymentsInterface;
use Bold\CheckoutPaymentBooster\Model\Http\SharedSecretAuthorization;
use Bold\CheckoutPaymentBooster\Model\Order\UpdatePayments\CancelOrder;
use Bold\CheckoutPaymentBooster\Model\Order\UpdatePayments\CreateCreditMemo;
use Bold\CheckoutPaymentBooster\Model\Order\UpdatePayments\CreateInvoice;
use Bold\CheckoutPaymentBooster\Model\ResourceModel\GetWebsiteIdByShopId;
use Magento\Framework\Exception\AuthorizationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

class UpdatePayments implements UpdatePaymentsInterface
{
    private const FINANCIAL_STATUS_PAID = 'paid';
    private const FINANCIAL_STATUS_REFUNDED = 'refunded';
    private const FINANCIAL_STATUS_CANCELLED = 'cancelled';

    /**
     * @var SharedSecretAuthorization
     */
    private $sharedSecretAuthorization;

    /**
     * @var GetWebsiteIdByShopId
     */
    private $getWebsiteIdByShopId;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var GetOrderPublicId
     */
    private $getOrderPublicId;

    /**
     * @var CreateInvoice
     */
    private $createInvoice;

    /**
     * @var CreateCreditMemo
     */
    private $createCreditMemo;

    /**
     * @var CancelOrder
     */
    private $cancelOrder;

    /**
     * @var ResultInterfaceFactory
     */
    private $responseFactory;

    /**
     * @param ResultInterfaceFactory $responseFactory
     * @param SharedSecretAuthorization $sharedSecretAuthorization
     * @param GetWebsiteIdByShopId $getWebsiteIdByShopId
     * @param OrderRepositoryInterface $orderRepository
     * @param GetOrderPublicId $getOrderPublicId
     * @param CreateInvoice $createInvoice
     * @param CreateCreditMemo $createCreditMemo
     * @param CancelOrder $cancelOrder
     */
    public function __construct(
        ResultInterfaceFactory $responseFactory,
        SharedSecretAuthorization $sharedSecretAuthorization,
        GetWebsiteIdByShopId      $getWebsiteIdByShopId,
        OrderRepositoryInterface  $orderRepository,
        GetOrderPublicId          $getOrderPublicId,
        CreateInvoice             $createInvoice,
        CreateCreditMemo          $createCreditMemo,
        CancelOrder               $cancelOrder
    ) {
        $this->responseFactory = $responseFactory;
        $this->sharedSecretAuthorization = $sharedSecretAuthorization;
        $this->getWebsiteIdByShopId = $getWebsiteIdByShopId;
        $this->orderRepository = $orderRepository;
        $this->getOrderPublicId = $getOrderPublicId;
        $this->createInvoice = $createInvoice;
        $this->createCreditMemo = $createCreditMemo;
        $this->cancelOrder = $cancelOrder;
    }

    /**
     * @inheritDoc
     */
    public function update(
        string $shopId,
        string $publicOrderId,
        string $platformFriendlyId,
        string $financialStatus
    ): ResultInterface {
        $websiteId = $this->getWebsiteIdByShopId->getWebsiteId($shopId);
        // Do not remove this check until resource authorized by ACL.
        if (!$this->sharedSecretAuthorization->isAuthorized($websiteId)) {
            // Shared secret authorization failed.
            throw new AuthorizationException(__('The consumer isn\'t authorized to access resource.'));
        }
        $order = $this->orderRepository->get($platformFriendlyId);
        $orderWebsiteId = (int)$order->getStore()->getWebsiteId();
        if ($orderWebsiteId !== $websiteId) {
            // Order website does not match shared secret website.
            throw new AuthorizationException(__('The consumer isn\'t authorized to access resource.'));
        }
        $orderStoredPublicId = $this->getOrderPublicId->execute($order);
        if ($orderStoredPublicId !== $publicOrderId) {
            throw new LocalizedException(__('Public Order ID does not match.'));
        }
        $this->processUpdate($order, $financialStatus);

        return $this->responseFactory->create(
            [
                'platformId' => $order->getId(),
                'platformFriendlyId' => $order->getIncrementId(),
                'platformCustomerId' => $order->getCustomerId() ?: null,
            ]
        );
    }

    /**
     * @param OrderInterface $order
     * @param string $financialStatus
     * @return void
     * @throws LocalizedException
     */
    public function processUpdate(OrderInterface $order, string $financialStatus): void
    {
        switch ($financialStatus) {
            case self::FINANCIAL_STATUS_PAID:
                $this->createInvoice->execute($order);
                break;
            case self::FINANCIAL_STATUS_REFUNDED:
                $this->createCreditMemo->execute($order);
                break;
            case self::FINANCIAL_STATUS_CANCELLED:
                $this->cancelOrder->execute($order);
                break;
            default:
                throw new LocalizedException(__('Unknown financial status.'));
        }
    }
}
