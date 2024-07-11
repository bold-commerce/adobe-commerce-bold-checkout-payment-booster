<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\UI\Payment;

use Bold\Checkout\Api\Http\ClientInterface;
use Bold\Checkout\Model\Payment\Gateway\Service;
use Bold\CheckoutPaymentBooster\Model\Config;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session;
use Magento\Directory\Model\AllowedCountries;
use Magento\Directory\Model\Country;
use Magento\Directory\Model\ResourceModel\Country\CollectionFactory;
use Magento\Framework\Exception\LocalizedException;

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
     * @var ClientInterface
     */
    private $client;

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
     * @param Session $checkoutSession
     * @param ClientInterface $client
     * @param Config $config
     * @param AllowedCountries $allowedCountries
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        Session $checkoutSession,
        ClientInterface $client,
        Config $config,
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
        if ($publicOrderId === null || $jwtToken === null) {
            return [];
        }
        $alternativePaymentMethods = $boldCheckoutData['data']['initial_data']['alternative_payment_methods'] ?? [];
        return [
            'bold' => [
                'payment_booster' => [
                    'jwtToken' => $jwtToken,
                    'url' => $this->getBoldStorefrontUrl($websiteId, $publicOrderId),
                    'shopId' => $shopId,
                    'publicOrderId' => $publicOrderId,
                    'countries' => $this->getAllowedCountries(),
                    'alternativePaymentMethods' => $alternativePaymentMethods,
                    'payment' => [
                        'iframeSrc' => $this->getIframeSrc($publicOrderId, $jwtToken, $websiteId),
                        'method' => Service::CODE,
                    ],
                ],
            ],
        ];
    }

    /**
     * Get iframe src.
     *
     * @param string|null $publicOrderId
     * @param string|null $jwtToken
     * @param int $websiteId
     * @return string|null
     */
    private function getIframeSrc(
        ?string $publicOrderId,
        ?string $jwtToken,
        int $websiteId
    ): ?string {
        if (!$publicOrderId || !$jwtToken) {
            return null;
        }

        return $this->client->getUrl($websiteId, 'payments/iframe?token=' . $jwtToken);
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
