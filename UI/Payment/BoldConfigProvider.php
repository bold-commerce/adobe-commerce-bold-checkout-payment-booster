<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\UI\Payment;

use Bold\Checkout\Api\Http\ClientInterface;
use Bold\Checkout\Model\ConfigInterface;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session;
use Magento\Directory\Model\AllowedCountries;
use Magento\Directory\Model\Country;
use Magento\Directory\Model\ResourceModel\Country\CollectionFactory;

/**
 * Config provider for Bold Payments.
 */
class BoldConfigProvider implements ConfigProviderInterface
{
    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @var ConfigInterface
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
    private $countries;

    /**
     * @param Session $checkoutSession
     * @param ClientInterface $client
     * @param ConfigInterface $config
     * @param AllowedCountries $allowedCountries
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        Session $checkoutSession,
        ClientInterface $client,
        ConfigInterface $config,
        AllowedCountries $allowedCountries,
        CollectionFactory $collectionFactory
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->client = $client;
        $this->config = $config;
        $this->allowedCountries = $allowedCountries;
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * @inheirtDoc
     */
    public function getConfig(): array
    {
        $boldCheckoutData = $this->checkoutSession->getBoldCheckoutData();
        if (!$boldCheckoutData) {
            return [];
        }

        $quote = $this->checkoutSession->getQuote();
        $websiteId = (int)$quote->getStore()->getWebsiteId();
        $shopId = $this->config->getShopId($websiteId);
        $publicOrderId = $boldCheckoutData['data']['public_order_id'] ?? null;
        $jwtToken = $boldCheckoutData['data']['jwt_token'] ?? null;
        $alternativePaymentMethods = $boldCheckoutData['data']['initial_data']['alternative_payment_methods'] ?? [];

        return [
            'bold' => [
                'jwtToken' => $jwtToken,
                'url' => $this->client->getUrl($websiteId, ''),
                'shopId' => $shopId,
                'publicOrderId' => $publicOrderId,
                'countries' => $this->getAllowedCountries(),
                'alternativePaymentMethods' => $alternativePaymentMethods,
                'origin' => rtrim($this->config->getApiUrl($websiteId), '/'),
            ],
        ];
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
