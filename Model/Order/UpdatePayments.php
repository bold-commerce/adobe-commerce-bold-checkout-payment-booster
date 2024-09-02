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
use Bold\CheckoutPaymentBooster\Model\OrderExtensionDataRepository;
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
     * @var OrderExtensionDataRepository
     */
    private $orderExtensionDataRepository;

    /**
     * @param ResultInterfaceFactory $responseFactory
     * @param SharedSecretAuthorization $sharedSecretAuthorization
     * @param GetWebsiteIdByShopId $getWebsiteIdByShopId
     * @param OrderRepositoryInterface $orderRepository
     * @param CreateInvoice $createInvoice
     * @param CreateCreditMemo $createCreditMemo
     * @param CancelOrder $cancelOrder
     * @param OrderExtensionDataRepository $orderExtensionDataRepository
     */
    public function __construct(
        ResultInterfaceFactory       $responseFactory,
        SharedSecretAuthorization    $sharedSecretAuthorization,
        GetWebsiteIdByShopId         $getWebsiteIdByShopId,
        OrderRepositoryInterface     $orderRepository,
        CreateInvoice                $createInvoice,
        CreateCreditMemo             $createCreditMemo,
        CancelOrder                  $cancelOrder,
        OrderExtensionDataRepository $orderExtensionDataRepository
    ) {
        $this->responseFactory = $responseFactory;
        $this->sharedSecretAuthorization = $sharedSecretAuthorization;
        $this->getWebsiteIdByShopId = $getWebsiteIdByShopId;
        $this->orderRepository = $orderRepository;
        $this->createInvoice = $createInvoice;
        $this->createCreditMemo = $createCreditMemo;
        $this->cancelOrder = $cancelOrder;
        $this->orderExtensionDataRepository = $orderExtensionDataRepository;
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
        $orderExtensionData = $this->orderExtensionDataRepository->getByOrderId((int)$order->getId());
        $orderStoredPublicId = $orderExtensionData ? $orderExtensionData->getPublicId() : null;
        if (!$orderStoredPublicId || $orderStoredPublicId !== $publicOrderId) {
            throw new LocalizedException(__('Public Order ID does not match.'));
        }
        $this->processUpdate($order, $orderExtensionData, $financialStatus);

        return $this->responseFactory->create(
            [
                'platformId' => $order->getId(),
                'platformFriendlyId' => $order->getIncrementId(),
                'platformCustomerId' => $order->getCustomerId() ?: null,
            ]
        );
    }

    /**
     * TODO
     *
     * @param OrderInterface $order
     * @param string $financialStatus
     * @return void
     * @throws LocalizedException
     */
    private function processUpdate(OrderInterface $order, OrderExtensionData $orderExtensionData, string $financialStatus): void
    {
        switch ($financialStatus) {
            case self::FINANCIAL_STATUS_PAID:
                if ($orderExtensionData->getCaptureAuthority() !== OrderExtensionData::AUTHORITY_LOCAL) {
                    $this->createInvoice->execute($order);
                    $orderExtensionData->setCaptureAuthority(OrderExtensionData::AUTHORITY_REMOTE);
                }
                break;
            case self::FINANCIAL_STATUS_REFUNDED:
                if ($orderExtensionData->getRefundAuthority() !== OrderExtensionData::AUTHORITY_LOCAL) {
                    $this->createCreditMemo->execute($order);
                    $orderExtensionData->setRefundAuthority(OrderExtensionData::AUTHORITY_REMOTE);
                }
                break;
            case self::FINANCIAL_STATUS_CANCELLED:
                if ($orderExtensionData->getCancelAuthority() !== OrderExtensionData::AUTHORITY_LOCAL) {
                    $this->cancelOrder->execute($order);
                    $orderExtensionData->setCancelAuthority(OrderExtensionData::AUTHORITY_REMOTE);
                }
                break;
            default:
                throw new LocalizedException(__('Unknown financial status.'));
        }
        $this->orderExtensionDataRepository->save($orderExtensionData);
    }
}
