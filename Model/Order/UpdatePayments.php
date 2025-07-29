<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Order;

use Bold\CheckoutPaymentBooster\Api\Data\Order\Payment\PaymentInterface;
use Bold\CheckoutPaymentBooster\Api\Data\Order\Payment\ResultInterface;
use Bold\CheckoutPaymentBooster\Api\Data\Order\Payment\ResultInterfaceFactory;
use Bold\CheckoutPaymentBooster\Api\Order\UpdatePaymentsInterface;
use Bold\CheckoutPaymentBooster\Model\Http\SharedSecretAuthorization;
use Bold\CheckoutPaymentBooster\Model\Order\UpdatePayments\CancelOrder;
use Bold\CheckoutPaymentBooster\Model\Order\UpdatePayments\CreateCreditMemo;
use Bold\CheckoutPaymentBooster\Model\Order\UpdatePayments\CreateInvoice;
use Bold\CheckoutPaymentBooster\Model\OrderExtensionDataRepository;
use Bold\CheckoutPaymentBooster\Model\ResourceModel\GetWebsiteIdByShopId;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\AuthorizationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

class UpdatePayments implements UpdatePaymentsInterface
{
    private const FINANCIAL_STATUS_PAID = 'paid';
    private const FINANCIAL_STATUS_REFUNDED = 'refunded';
    private const FINANCIAL_STATUS_PARTIALLY_REFUNDED = 'partially_refunded';
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
        ResultInterfaceFactory $responseFactory,
        SharedSecretAuthorization $sharedSecretAuthorization,
        GetWebsiteIdByShopId $getWebsiteIdByShopId,
        OrderRepositoryInterface $orderRepository,
        CreateInvoice $createInvoice,
        CreateCreditMemo $createCreditMemo,
        CancelOrder $cancelOrder,
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
        string $financialStatus,
        int $platformOrderId,
        array $payments
    ): ResultInterface {

        /** Start - Temporary debug code **/
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/bold_checkout_payment_booster.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        /** End - Temporary debug code **/

        $logger->info("Update Payment: ");
        $logger->info("Shop Id: " . $shopId);
        $logger->info("Financial status " . $financialStatus);
        $logger->info("Platform Order ID: " . $platformOrderId);
        $logger->info("Payments:" . json_encode($payments));


        $websiteId = $this->getWebsiteIdByShopId->getWebsiteId($shopId);
        // Do not remove this check until resource authorized by ACL.
        if (!$this->sharedSecretAuthorization->isAuthorized($websiteId)) {
            // Shared secret authorization failed.
            throw new AuthorizationException(__('The consumer isn\'t authorized to access resource.'));
        }
        $orderExtensionData = $this->orderExtensionDataRepository->getByOrderId($platformOrderId);
        if (!$orderExtensionData->getPublicId()) {
            throw new LocalizedException(__('Public Order ID does not match.'));
        }
        /** @var OrderInterface&Order $order */
        $order = $this->orderRepository->get($platformOrderId);
        $this->processUpdate($order, $orderExtensionData, $financialStatus, $payments);

        return $this->responseFactory->create(
            [
                'platformId' => $order->getId(),
                'platformFriendlyId' => $order->getIncrementId(),
                'platformCustomerId' => $order->getCustomerId() ?: null,
            ]
        );
    }

    /**
     * Process update based on financial status.
     *
     * @param OrderInterface&Order $order
     * @param OrderExtensionData $orderExtensionData
     * @param string $financialStatus
     * @param PaymentInterface[] $payments
     * @return void
     * @throws LocalizedException
     * @throws AlreadyExistsException
     */
    private function processUpdate(
        OrderInterface $order,
        OrderExtensionData $orderExtensionData,
        string $financialStatus,
        array $payments
    ): void {

        /** Start - Temporary debug code **/
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/bold_checkout_payment_booster.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        /** End - Temporary debug code **/

        $logger->info("Update Payment: ");
        $logger->info("Order Id: " . $order->getIncrementId());
        $logger->info("Financial status " . $financialStatus);
        $logger->info("Payments:" . json_encode($payments));


        switch ($financialStatus) {
            case self::FINANCIAL_STATUS_PAID:
                if (!$orderExtensionData->getIsCaptureInProgress()) {
                    $this->createInvoice->execute($order, $payments);
                    $logger->info("createInvoice");
                }
                break;
            case self::FINANCIAL_STATUS_REFUNDED:
            case self::FINANCIAL_STATUS_PARTIALLY_REFUNDED:
                if (!$orderExtensionData->getIsRefundInProgress()) {
                    $this->createCreditMemo->execute($order);
                    $logger->info("createCreditMemo");
                }
                break;
            case self::FINANCIAL_STATUS_CANCELLED:
                if (!$orderExtensionData->getIsCancelInProgress()) {
                    $this->cancelOrder->execute($order);
                    $logger->info("cancelOrder");
                }
                break;
            default:
                throw new LocalizedException(__('Unknown financial status.'));
        }
    }
}
