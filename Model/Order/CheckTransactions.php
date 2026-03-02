<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Order;

use Bold\CheckoutPaymentBooster\Model\MagentoQuoteBoldOrderRepository;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Api\TransactionRepositoryInterface;

class CheckTransactions
{
    /** @var MagentoQuoteBoldOrderRepository  */
    private $magentoQuoteBoldOrderRepository;

    /** @var TransactionRepositoryInterface */
    private $transactionRepository;

    /** @var SearchCriteriaBuilder */
    private $searchCriteriaBuilder;

    /**
     * Constructor
     */
    public function __construct(
        MagentoQuoteBoldOrderRepository $magentoQuoteBoldOrderRepository,
        TransactionRepositoryInterface $transactionRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->magentoQuoteBoldOrderRepository = $magentoQuoteBoldOrderRepository;
        $this->transactionRepository = $transactionRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * Check whether an authorization transaction exists in Magento's sales_payment_transaction table.
     *
     * This is independent of the Bold lifecycle table and provides a cross-check:
     * if saveTransactionData() partially succeeded (e.g., addTransaction() failed after
     * the API call), the Bold table could show auth_full_at while this table would be empty.
     *
     * @param OrderInterface $order
     * @return bool
     */
    public function hasAuthTransaction(OrderInterface $order): bool
    {
        if (!$order->getEntityId()) {
            return false;
        }

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('order_id', $order->getEntityId())
            ->addFilter('txn_type', TransactionInterface::TYPE_AUTH)
            ->create();

        return $this->transactionRepository->getList($searchCriteria)->getTotalCount() > 0;
    }

    /**
     * Returns true when the Bold lifecycle table has a relation record for the quote
     * (regardless of auth timestamp). Used to distinguish "no record at all" from
     * "record exists but not yet authorized".
     *
     * @param string $quoteId
     * @return bool
     */
    public function hasRelationRecord(string $quoteId): bool
    {
        try {
            $this->magentoQuoteBoldOrderRepository->getByQuoteId($quoteId);
        } catch (NoSuchEntityException $e) {
            return false;
        }
        return true;
    }

    /**
     * Returns true when bold_booster_magento_quote_bold_order.successful_auth_full_at
     * is non-null for the given quote — i.e. Bold recorded a successful authorization.
     *
     * @param string $quoteId
     * @return bool
     */
    public function getAuthTransactionFromLifecycle(string $quoteId): bool
    {
        try {
            $relation = $this->magentoQuoteBoldOrderRepository->getByQuoteId($quoteId);
            return $relation->getSuccessfulAuthFullAt() !== null;
        } catch (NoSuchEntityException $e) {
            return false;
        }
    }
}
