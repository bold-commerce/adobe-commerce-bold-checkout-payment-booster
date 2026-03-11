<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Payment;

use Bold\CheckoutPaymentBooster\Api\MagentoQuoteBoldOrderRepositoryInterface;
use Bold\CheckoutPaymentBooster\Model\Http\BoldClient;
use Bold\CheckoutPaymentBooster\Model\Order\UpdatePayments\TransactionComment;
use Magento\Framework\Exception\LocalizedException;

/**
 * Fully authorize payments.
 */
class Authorize
{
    private const PATH_PAYMENTS_AUTH = 'checkout/orders/{{shopId}}/%s/payments/auth/full';

    /**
     * @var BoldClient
     */
    private $client;

    /** @var MagentoQuoteBoldOrderRepositoryInterface */
    private $magentoQuoteBoldOrderRepository;

    /**
     * @param BoldClient $client
     * @param MagentoQuoteBoldOrderRepositoryInterface $magentoQuoteBoldOrderRepository
     * @param TransactionComment $transactionComment
     */
    public function __construct(
        BoldClient $client,
        MagentoQuoteBoldOrderRepositoryInterface $magentoQuoteBoldOrderRepository
    ) {
        $this->client = $client;
        $this->magentoQuoteBoldOrderRepository = $magentoQuoteBoldOrderRepository;
    }

    /**
     * Fully authorize payments.
     *
     * @param string $publicOrderId
     * @param int $websiteId
     * @param string $quoteId
     * @return array{
     *     data: array{
     *         transactions: array{
     *             transaction_id: string,
     *             tender_details: array{
     *                 account: string,
     *                 email: string
     *             }
     *         }[]
     *     }
     * }
     * @throws LocalizedException
     */
    public function execute(string $publicOrderId, int $websiteId, string $quoteId): array
    {
        $url = sprintf(self::PATH_PAYMENTS_AUTH, $publicOrderId);
        $result = $this->client->post($websiteId, $url, []);
        if ($result->getErrors()) {
            $message = isset(current($result->getErrors())['message'])
                ? __(current($result->getErrors())['message'])
                : __('The payment cannot be authorized.');
            throw new LocalizedException($message);
        }
        $this->magentoQuoteBoldOrderRepository->saveAuthorizedAt($quoteId);
        return $result->getBody();
    }
}
