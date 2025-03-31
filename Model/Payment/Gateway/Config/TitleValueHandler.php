<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Payment\Gateway\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Payment\Gateway\Config\ValueHandlerInterface;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 *  Bold Payment Title Value Handler.
 */
class TitleValueHandler implements ValueHandlerInterface
{
    /**
     * @var ScopeConfigInterface
     */
    private $config;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var string
     */
    private $path;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @param ScopeConfigInterface $config
     * @param StoreManagerInterface $storeManager
     * @param string $path
     */
    public function __construct(
        ScopeConfigInterface $config,
        StoreManagerInterface $storeManager,
        SerializerInterface $serializer,
        string $path
    ) {
        $this->config = $config;
        $this->storeManager = $storeManager;
        $this->path = $path;
        $this->serializer = $serializer;
    }

    /**
     * @inheritDoc
     */
    public function handle(array $subject, $storeId = null)
    {
        /** @var PaymentDataObject $paymentObject */
        $paymentObject = $subject['payment'] ?? null;
        $websiteId = (int)$this->storeManager->getWebsite()->getId();
        if (!$paymentObject || !$paymentObject->getPayment()) {
            if (!$websiteId) {
                $store = $this->storeManager->getDefaultStoreView();
                $websiteId = (int)$store->getWebsiteId();
            }
            return $this->config->getValue($this->path, ScopeInterface::SCOPE_WEBSITE, $websiteId);
        }
        $payment = $paymentObject->getPayment();
        if ($payment->getAdditionalInformation('card_details')) {
            $cardDetails = $this->serializer->unserialize($payment->getAdditionalInformation('card_details'));
            if (isset($cardDetails['brand']) && isset($cardDetails['last_four'])) {
                return ucfirst($cardDetails['brand']) . ': ending in ' . $cardDetails['last_four'];
            }
            if (isset($cardDetails['account']) && isset($cardDetails['email'])) {
                return 'PayPal: ' . $cardDetails['email'];
            }
        }
        return $this->config->getValue($this->path, ScopeInterface::SCOPE_WEBSITE, $websiteId);
    }
}
