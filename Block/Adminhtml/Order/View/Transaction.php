<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Block\Adminhtml\Order\View;

use Bold\CheckoutPaymentBooster\Model\Config;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json as Serializer;

class Transaction extends \Magento\Backend\Block\Template
{
    /** @var Registry  */
    private $registry;

    /**
     * @var Serializer
     */
    private $serializer;

    /**
     * Constructor
     *
     * @param Context $context
     * @param Registry $registry
     * @param Serializer $serializer
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        Serializer $serializer,
        array $data = []
    ) {
        $this->registry = $registry;
        $this->serializer = $serializer;
        parent::__construct($context, $data);
    }

    /**
     * Check if is to show retry button - only for bold order
     *
     * @return bool
     */
    public function isShowTransaction(): bool
    {
        $order = $this->getOrder();
        $method = $order->getPayment()->getMethod();

        return in_array($method, Config::BOLD_PAYMENT_METHODS_CODE, true);
    }

    /**
     * Get order
     *
     * @return mixed|null
     */
    public function getOrder()
    {
        return $this->registry->registry('current_order');
    }

    /**
     * Get Transaction Info from additional information
     *
     * @return array<int, array<string, string>>
     */
    public function getTransactionInfo(): array
    {
        $transactions = $this->getOrder()->getPayment()->getAdditionalInformation('bold_transactions');
        if (is_string($transactions)) {
            $decoded = $this->serializer->unserialize($transactions);
            return is_array($decoded) ? $decoded : [];
        }
        return is_array($transactions) ? $transactions : [];
    }

    /**
     * Retrieves the last transaction from the transaction information.
     *
     * @return array<string, string>
     */
    public function getLastTransaction(): array
    {
        $transactions = $this->getTransactionInfo();
        $firstTransaction = reset($transactions);

        return is_array($firstTransaction) ? $firstTransaction : [];
    }
}
