<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\UI;

use Bold\CheckoutPaymentBooster\Model\CheckoutData;
use Bold\CheckoutPaymentBooster\Model\Config;
use Bold\CheckoutPaymentBooster\Model\Payment\Gateway\Service;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Directory\Model\AllowedCountries;
use Magento\Directory\Model\Country;
use Magento\Directory\Model\ResourceModel\Country\CollectionFactory;
use Magento\Config\Model\Config\Source\Nooptreq as NooptreqSource;
use Psr\Log\LoggerInterface;

/**
 * Config provider for Payment Booster.
 */
class PaymentBoosterConfigProvider implements ConfigProviderInterface
{
    /**
     * @var CheckoutData
     */
    private $checkoutData;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var AllowedCountries
     */
    private $allowedCountries;

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var array
     */
    private $countries = [];

    /**
     * @param CheckoutData $checkoutData
     * @param Config $config
     * @param AllowedCountries $allowedCountries
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        CheckoutData $checkoutData,
        Config $config,
        AllowedCountries $allowedCountries,
        CollectionFactory $collectionFactory,
        LoggerInterface $logger
    ) {
        $this->checkoutData = $checkoutData;
        $this->config = $config;
        $this->allowedCountries = $allowedCountries;
        $this->collectionFactory = $collectionFactory;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function getConfig(): array
    {
        if (!$this->checkoutData->getPublicOrderId()) {
            $errorMsg = "No public order ID.";
            $this->logger->critical('Error in PaymentBoosterConfigProvider->getConfig(): '.$errorMsg);
            return [];
        }

        $quote = $this->checkoutData->getQuote();
        $websiteId = (int)$quote->getStore()->getWebsiteId();
        $shopId = $this->config->getShopId($websiteId);
        $publicOrderId = $this->checkoutData->getPublicOrderId();
        $jwtToken = $this->checkoutData->getJwtToken();
        $epsAuthToken = $this->checkoutData->getEpsAuthToken();
        $epsGatewayId = $this->checkoutData->getEpsGatewayId();
        if ($jwtToken === null || $epsAuthToken === null || $epsGatewayId === null) {
            $errorMsgs = [];
            if ($jwtToken === null) {
                $errorMsgs[] = '$jwtToken is null.';
            }

            if ($epsAuthToken === null) {
                $errorMsgs[] = '$epsAuthToken is null.';
            }

            if ($epsGatewayId === null) {
                $errorMsgs[] = '$epsGatewayId is null.';
            }

            $this->logger->critical('Error in PaymentBoosterConfigProvider->getConfig(): '.implode(', ', $errorMsgs));
            return [];
        }

        $configurationGroupLabel = $this->config->getConfigurationGroupLabel($websiteId);
        if (empty($configurationGroupLabel)) {
            $configurationGroupLabel = parse_url($quote->getStore()->getBaseUrl())['host'] ?? '';
        }

        return [
            'bold' => [
                'epsAuthToken' => $epsAuthToken,
                'configurationGroupLabel' => $configurationGroupLabel,
                'epsUrl' => $this->config->getEpsUrl($websiteId),
                'epsStaticUrl' => $this->config->getStaticEpsUrl($websiteId),
                'gatewayId' => $epsGatewayId,
                'jwtToken' => $jwtToken,
                'url' => $this->getBoldStorefrontUrl($websiteId, $publicOrderId),
                'shopId' => $shopId,
                'publicOrderId' => $publicOrderId,
                'countries' => $this->getAllowedCountries(),
                'origin' => rtrim($this->config->getApiUrl($websiteId), '/'),
                'epsUrl' => rtrim($this->config->getEpsUrl($websiteId), '/'),
                'shopUrl' => $quote->getStore()->getBaseUrl(),
                'shopName' => $quote->getStore()->getFrontendName(),
                'isPhoneRequired' => $quote->getStore()->getConfig('customer/address/telephone_show') === NooptreqSource::VALUE_REQUIRED,
                'isExpressPayEnabled' => $this->config->isExpressPayEnabled($websiteId),
                'paymentBooster' => [
                    'payment' => [
                        'method' => Service::CODE,
                    ],
                ],
            ],
        ];
    }

    /**
     * Get Bold Storefront URL.
     *
     * @param int $websiteId
     * @return string
     */
    private function getBoldStorefrontUrl(int $websiteId, string $publicOrderId): string
    {
        $apiUrl = $this->config->getApiUrl($websiteId) . 'checkout/storefront/';
        return $apiUrl . $this->config->getShopId($websiteId) . '/' . $publicOrderId . '/';
    }

    /**
     * Get allowed countries for Billing address mapping.
     *
     * @return Country[]
     */
    private function getAllowedCountries(): array
    {
        if ($this->countries) {
            return $this->countries;
        }
        $allowedCountries = $this->allowedCountries->getAllowedCountries();
        $countriesCollection = $this->collectionFactory->create()->addFieldToFilter(
            'country_id',
            ['in' => $allowedCountries]
        );
        $this->countries = $countriesCollection->toOptionArray(false);

        return $this->countries;
    }
}
