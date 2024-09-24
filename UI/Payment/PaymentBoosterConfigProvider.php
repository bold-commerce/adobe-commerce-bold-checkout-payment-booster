<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\UI\Payment;

use Bold\CheckoutPaymentBooster\Model\Config;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session;
use Magento\Directory\Model\AllowedCountries;
use Magento\Directory\Model\Country;
use Magento\Directory\Model\ResourceModel\Country\CollectionFactory;

/**
 * Config provider for Payment Booster.
 */
class PaymentBoosterConfigProvider implements ConfigProviderInterface
{
    /**
     * @var Session
     */
    private $checkoutSession;

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
     * @var array
     */
    private $countries = [];

    /**
     * @param Session $checkoutSession
     * @param Config $config
     * @param AllowedCountries $allowedCountries
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        Session $checkoutSession,
        Config $config,
        AllowedCountries $allowedCountries,
        CollectionFactory $collectionFactory
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->config = $config;
        $this->allowedCountries = $allowedCountries;
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * @inheritdoc
     */
    public function getConfig(): array
    {
        $boldCheckoutData = $this->checkoutSession->getBoldCheckoutData();
        $quote = $this->checkoutSession->getQuote();
        $websiteId = (int)$quote->getStore()->getWebsiteId();

        if (!$boldCheckoutData
            || !$this->config->isPaymentBoosterEnabled($websiteId)
        ) {
            return [];
        }

        $quote = $this->checkoutSession->getQuote();
        $websiteId = (int)$quote->getStore()->getWebsiteId();
        $shopId = $this->config->getShopId($websiteId);
        $publicOrderId = $boldCheckoutData['data']['public_order_id'] ?? null;
        $jwtToken = $boldCheckoutData['data']['jwt_token'] ?? null;
        $epsAuthToken = $boldCheckoutData['data']['flow_settings']['eps_auth_token'] ?? null;
        $epsGatewayId = $boldCheckoutData['data']['flow_settings']['eps_gateway_id'] ?? null;
        if ($publicOrderId === null || $jwtToken === null) {
            return [];
        }
        $alternativePaymentMethods = $boldCheckoutData['data']['initial_data']['alternative_payment_methods'] ?? [];
        return [
            'bold' => [
                'epsAuthToken' => $epsAuthToken,
                'configurationGroupLabel' => $this->config->getConfigurationGroupLabel($websiteId),
                'epsUrl' => $this->config->getEpsUrl($websiteId),
                'epsStaticUrl' => 'https://static-eps.secure.staging.boldcommerce.com',
                'gatewayId' => $epsGatewayId,
                'jwtToken' => $jwtToken,
                'url' => $this->getBoldStorefrontUrl($websiteId, $publicOrderId),
                'shopId' => $shopId,
                'publicOrderId' => $publicOrderId,
                'countries' => $this->getAllowedCountries(),
                'alternativePaymentMethods' => $alternativePaymentMethods,
                'origin' => rtrim($this->config->getApiUrl($websiteId), '/'),
                'paymentBooster' => [
                    'payment' => [
                        'method' => 'bold',
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
