<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Plugin\Sales\CreditmemoManagement;

use Bold\CheckoutPaymentBooster\Model\Order\CheckPaymentMethod;
use Bold\CheckoutPaymentBooster\Model\Order\OrderExtensionData;
use Bold\CheckoutPaymentBooster\Model\OrderExtensionDataRepository;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\CreditmemoManagementInterface;
use Magento\Sales\Api\Data\CreditmemoInterface;

/**
 * Set refund authority.
 *
 * Plugin required, as \Bold\CheckoutPaymentBooster\Model\Payment\Gateway\Command\RefundPayment::execute call
 * is wrapped in transaction.
 */
class SetRefundAuthorityPlugin
{
    /**
     * @var OrderExtensionDataRepository
     */
    private $orderExtensionDataRepository;

    /**
     * @var CheckPaymentMethod
     */
    private $checkPaymentMethod;

    /**
     * @param OrderExtensionDataRepository $orderExtensionDataRepository
     * @param CheckPaymentMethod $checkPaymentMethod
     */
    public function __construct(
        OrderExtensionDataRepository $orderExtensionDataRepository,
        CheckPaymentMethod           $checkPaymentMethod
    ) {
        $this->orderExtensionDataRepository = $orderExtensionDataRepository;
        $this->checkPaymentMethod = $checkPaymentMethod;
    }

    /**
     * Set refund authority.
     *
     * @param CreditmemoManagementInterface $subject
     * @param CreditmemoInterface $creditmemo
     * @param bool $offlineRequested
     * @return void
     * @throws LocalizedException
     * @throws AlreadyExistsException
     */
    public function beforeRefund(
        CreditmemoManagementInterface $subject,
        CreditmemoInterface           $creditmemo,
                                      $offlineRequested = false
    ): void {
        $order = $creditmemo->getOrder();
        if (!$order || !$this->checkPaymentMethod->isBold($order)) {
            return;
        }
        $orderExtensionData = $this->orderExtensionDataRepository->getByOrderId((int)$creditmemo->getOrderId());
        if (!$orderExtensionData->getPublicId()) {
            throw new LocalizedException(__('Order public id is not set.'));
        }
        if ($orderExtensionData->getRefundAuthority() === OrderExtensionData::AUTHORITY_REMOTE) {
            throw new LocalizedException(__('Payment cannot be refunded.'));
        }
        $orderExtensionData->setRefundAuthority(OrderExtensionData::AUTHORITY_LOCAL);
        $this->orderExtensionDataRepository->save($orderExtensionData);
    }
}
