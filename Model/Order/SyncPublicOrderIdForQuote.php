<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Order;

use Bold\CheckoutPaymentBooster\Api\MagentoQuoteBoldOrderRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;

/**
 * Persist the active Bold public order ID on the Magento quote relation (and extension when available).
 */
class SyncPublicOrderIdForQuote
{
    /**
     * @var MagentoQuoteBoldOrderRepositoryInterface
     */
    private $magentoQuoteBoldOrderRepository;

    /**
     * @param MagentoQuoteBoldOrderRepositoryInterface $magentoQuoteBoldOrderRepository
     */
    public function __construct(MagentoQuoteBoldOrderRepositoryInterface $magentoQuoteBoldOrderRepository)
    {
        $this->magentoQuoteBoldOrderRepository = $magentoQuoteBoldOrderRepository;
    }

    /**
     * @param string $publicOrderId
     * @param string $quoteId
     * @param CartInterface|null $quote
     * @return void
     */
    public function execute(string $publicOrderId, string $quoteId, ?CartInterface $quote = null): void
    {
        $this->magentoQuoteBoldOrderRepository->saveBoldQuotePublicOrderRelation($publicOrderId, $quoteId);

        if ($quote === null) {
            return;
        }

        $extensionAttributes = $quote->getExtensionAttributes();
        if ($extensionAttributes !== null) {
            $extensionAttributes->setBoldOrderId($publicOrderId);
        }
    }
}
