<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Order;

use Bold\CheckoutPaymentBooster\Api\MagentoQuoteBoldOrderRepositoryInterface;
use Bold\CheckoutPaymentBooster\Model\CheckoutData;
use Bold\CheckoutPaymentBooster\Model\Log\OrderTracker;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;

/**
 * Resolve Bold public order ID for hydrate/auth, reconciling session and quote sources.
 *
 * During active checkout the session is authoritative (payments bind to it). Quote extension
 * and DB are kept in sync; when they drift, the session value is reconciled onto the quote.
 */
class ResolvePublicOrderId
{
    /**
     * @var CheckoutData
     */
    private $checkoutData;

    /**
     * @var MagentoQuoteBoldOrderRepositoryInterface
     */
    private $magentoQuoteBoldOrderRepository;

    /**
     * @var SyncPublicOrderIdForQuote
     */
    private $syncPublicOrderIdForQuote;

    /**
     * @var OrderTracker
     */
    private $orderTracker;

    /**
     * @param CheckoutData $checkoutData
     * @param MagentoQuoteBoldOrderRepositoryInterface $magentoQuoteBoldOrderRepository
     * @param SyncPublicOrderIdForQuote $syncPublicOrderIdForQuote
     * @param OrderTracker $orderTracker
     */
    public function __construct(
        CheckoutData $checkoutData,
        MagentoQuoteBoldOrderRepositoryInterface $magentoQuoteBoldOrderRepository,
        SyncPublicOrderIdForQuote $syncPublicOrderIdForQuote,
        OrderTracker $orderTracker
    ) {
        $this->checkoutData = $checkoutData;
        $this->magentoQuoteBoldOrderRepository = $magentoQuoteBoldOrderRepository;
        $this->syncPublicOrderIdForQuote = $syncPublicOrderIdForQuote;
        $this->orderTracker = $orderTracker;
    }

    /**
     * @param Quote $quote
     * @return string|null
     */
    public function execute(Quote $quote): ?string
    {
        $quoteId = (string)$quote->getId();
        $websiteId = (int)$quote->getStore()->getWebsiteId();
        $fromSession = $this->normalize($this->checkoutData->getPublicOrderId());
        $extensionAttributes = $quote->getExtensionAttributes();
        $fromExtension = $extensionAttributes !== null
            ? $this->normalize($extensionAttributes->getBoldOrderId())
            : null;
        $fromDb = null;

        try {
            $fromDb = $this->normalize(
                $this->magentoQuoteBoldOrderRepository->getByQuoteId($quoteId)->getBoldOrderId()
            );
        } catch (NoSuchEntityException $e) {
            // No persisted quote ↔ Bold order relation yet.
        }

        if ($this->magentoQuoteBoldOrderRepository->isQuoteProcessed($quoteId)) {
            $this->orderTracker->trace($websiteId, 'resolve_public_order_id_processed_quote', [
                'quote_id' => $quoteId,
                'session_public_order_id' => $fromSession,
                'quote_ext_public_order_id' => $fromExtension,
                'db_public_order_id' => $fromDb,
                'resolved_public_order_id' => $fromSession,
            ]);

            return $fromSession;
        }

        if ($fromSession !== null && $this->magentoQuoteBoldOrderRepository->isPublicOrderCompleted($fromSession)) {
            $this->orderTracker->trace($websiteId, 'resolve_public_order_id_completed_session', [
                'quote_id' => $quoteId,
                'stale_session_public_order_id' => $fromSession,
                'quote_ext_public_order_id' => $fromExtension,
                'db_public_order_id' => $fromDb,
            ]);
            $fromSession = null;
        }

        if ($fromSession !== null && $fromExtension !== null && $fromSession !== $fromExtension) {
            $this->orderTracker->trace($websiteId, 'resolve_public_order_id_reconcile', [
                'quote_id' => $quoteId,
                'session_public_order_id' => $fromSession,
                'quote_ext_public_order_id' => $fromExtension,
                'db_public_order_id' => $fromDb,
            ]);
            $this->syncPublicOrderIdForQuote->execute($fromSession, $quoteId, $quote);

            return $fromSession;
        }

        $resolved = $fromSession ?? $fromExtension ?? $fromDb;

        if ($resolved !== null && $fromSession !== null && $resolved === $fromSession) {
            $this->syncPublicOrderIdForQuote->execute($fromSession, $quoteId, $quote);
        }

        $this->orderTracker->trace($websiteId, 'resolve_public_order_id', [
            'quote_id' => $quoteId,
            'session_public_order_id' => $fromSession,
            'quote_ext_public_order_id' => $fromExtension,
            'db_public_order_id' => $fromDb,
            'resolved_public_order_id' => $resolved,
            'public_order_ids_match' => $this->idsMatch($fromSession, $fromExtension, $fromDb),
        ]);

        return $resolved;
    }

    /**
     * @param string|null $id
     * @return string|null
     */
    private function normalize(?string $id): ?string
    {
        if ($id === null || $id === '') {
            return null;
        }

        return $id;
    }

    /**
     * @param string|null $fromSession
     * @param string|null $fromExtension
     * @param string|null $fromDb
     * @return bool
     */
    private function idsMatch(?string $fromSession, ?string $fromExtension, ?string $fromDb): bool
    {
        $ids = array_values(array_filter([$fromSession, $fromExtension, $fromDb], static function ($id) {
            return $id !== null && $id !== '';
        }));

        if (count($ids) <= 1) {
            return true;
        }

        return count(array_unique($ids)) === 1;
    }
}
