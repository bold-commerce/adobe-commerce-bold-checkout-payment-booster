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
    private const GET_COUNTRY_CODE_URL = 'https://shappify-cdn.com/cf_helper/get_country.php';

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
     * @param ConfigInterface $config
     * @param ClientInterface $client
     * @param AllowedCountries $allowedCountries
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        Session $checkoutSession,
        ConfigInterface $config,
        ClientInterface $client,
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
        $quote = $this->checkoutSession->getQuote();
        $websiteId = (int)$quote->getStore()->getWebsiteId();
        if (!$boldCheckoutData) {
            return [];
        }
        $shopId = $this->config->getShopId($websiteId);
        $publicOrderId = $boldCheckoutData['data']['public_order_id'] ?? null;
        $jwtToken = $boldCheckoutData['data']['jwt_token'] ?? null;

        return [
            'bold' => [
                'jwtToken' => $jwtToken,
                'url' => $this->client->getUrl($websiteId, ''),
                'shopId' => $shopId,
                'publicOrderId' => $publicOrderId,
                'countries' => $this->getAllowedCountries(),
                'getCountryCodeUrl' => self::GET_COUNTRY_CODE_URL,
            ],
        ];
    }

    /**
     * Get allowed countries for Billing address mapping.
     *
     * @return Country[]
     */
    public function getAllowedCountries(): array
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
