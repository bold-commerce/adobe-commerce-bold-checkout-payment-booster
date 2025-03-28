<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Plugin\Quote\Api;

use Bold\CheckoutPaymentBooster\Api\Data\MagentoQuoteBoldOrderInterface;
use Bold\CheckoutPaymentBooster\Api\Data\MagentoQuoteBoldOrderInterfaceFactory;
use Bold\CheckoutPaymentBooster\Api\MagentoQuoteBoldOrderRepositoryInterface;
use Bold\CheckoutPaymentBooster\Model\MagentoQuoteBoldOrder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;

class CartRepositoryInterfacePlugin
{
    /**
     * @var MagentoQuoteBoldOrderRepositoryInterface
     */
    private $magentoQuoteBoldOrderRepository;
    /**
     * @var MagentoQuoteBoldOrderInterfaceFactory
     */
    private $magentoQuoteBoldOrderInterfaceFactory;

    public function __construct(
        MagentoQuoteBoldOrderRepositoryInterface $magentoQuoteBoldOrderRepository,
        MagentoQuoteBoldOrderInterfaceFactory $magentoQuoteBoldOrderInterfaceFactory
    ) {
        $this->magentoQuoteBoldOrderRepository = $magentoQuoteBoldOrderRepository;
        $this->magentoQuoteBoldOrderInterfaceFactory = $magentoQuoteBoldOrderInterfaceFactory;
    }

    /**
     * @param CartRepositoryInterface $subject
     * @param CartInterface $result
     * @param int $cartId
     * @return CartInterface
     */
    public function afterGet(CartRepositoryInterface $subject, CartInterface $result, $cartId): CartInterface
    {
        $cartExtension = $result->getExtensionAttributes();

        if ($cartExtension === null || $cartExtension->getBoldOrderId() !== null) {
            return $result;
        }

        try {
            $magentoQuoteBoldOrder = $this->magentoQuoteBoldOrderRepository->getByQuoteId($cartId);
        } catch (NoSuchEntityException $e) {
            return $result;
        }

        if ($magentoQuoteBoldOrder->getBoldOrderId() === null) {
            return $result;
        }

        $cartExtension->setBoldOrderId($magentoQuoteBoldOrder->getBoldOrderId());

        return $result;
    }

    /**
     * @param CartRepositoryInterface $subject
     * @param void $result
     * @param CartInterface $quote
     * @return void
     */
    public function afterSave(CartRepositoryInterface $subject, $result, CartInterface $quote): void
    {
        $cartExtension = $quote->getExtensionAttributes();

        if ($cartExtension === null || $cartExtension->getBoldOrderId() === null) {
            return;
        }

        /** @var MagentoQuoteBoldOrderInterface&MagentoQuoteBoldOrder $magentoQuoteBoldOrder */
        $magentoQuoteBoldOrder = $this->magentoQuoteBoldOrderInterfaceFactory->create();

        $magentoQuoteBoldOrder->setQuoteId($quote->getId());
        $magentoQuoteBoldOrder->setBoldOrderId($cartExtension->getBoldOrderId());

        try {
            $this->magentoQuoteBoldOrderRepository->save($magentoQuoteBoldOrder);
        } catch (LocalizedException $localizedException) {// phpcs:ignore Magento2.CodeAnalysis.EmptyBlock.DetectedCatch
        }
    }
}
