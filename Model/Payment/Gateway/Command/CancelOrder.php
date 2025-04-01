<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Payment\Gateway\Command;

use Bold\CheckoutPaymentBooster\Model\OrderExtensionDataRepository;
use Bold\CheckoutPaymentBooster\Model\Payment\Gateway\Service;
use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\CommandInterface;

/**
 * Cancel bold order.
 */
class CancelOrder implements CommandInterface
{
    /**
     * @var Service
     */
    private $gatewayService;

    /**
     * @var OrderExtensionDataRepository
     */
    private $orderExtensionDataRepository;

    /**
     * @param Service $gatewayService
     * @param OrderExtensionDataRepository $orderExtensionDataRepository
     */
    public function __construct(
        Service $gatewayService,
        OrderExtensionDataRepository $orderExtensionDataRepository
    ) {
        $this->gatewayService = $gatewayService;
        $this->orderExtensionDataRepository = $orderExtensionDataRepository;
    }

    /**
     * @inheritDoc
     *
     * @throws Exception
     */
    public function execute(array $commandSubject): void
    {
        $paymentDataObject = $commandSubject['payment'];
        $payment = $paymentDataObject->getPayment();
        $order = $payment->getOrder();
        $orderExtensionData = $this->orderExtensionDataRepository->getByOrderId((int)$order->getId());
        if (!$orderExtensionData->getPublicId()) {
            throw new LocalizedException(__('Order public id is not set.'));
        }
        if ($orderExtensionData->getIsCancelInProgress()) {
            return;
        }
        $orderExtensionData->setIsCancelInProgress(true);
        $this->orderExtensionDataRepository->save($orderExtensionData);
        try {
            $this->gatewayService->cancel($order);
        } catch (Exception $e) {
            $orderExtensionData->setIsCancelInProgress(false);
            $this->orderExtensionDataRepository->save($orderExtensionData);
            throw $e;
        }
        $orderExtensionData->setIsCancelInProgress(false);
        $this->orderExtensionDataRepository->save($orderExtensionData);
    }
}
