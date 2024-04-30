<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\UI\Payment;

use Bold\Checkout\Api\Http\ClientInterface;
use Bold\Checkout\Model\ConfigInterface;
use Bold\Checkout\Model\Payment\Gateway\Service;
use Bold\CheckoutPaymentBooster\Model\Config as ModuleConfig;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session;
use Magento\Directory\Model\AllowedCountries;
use Magento\Directory\Model\Country;
use Magento\Directory\Model\ResourceModel\Country\CollectionFactory;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\Filesystem\Directory\ReadFactory;
use Magento\Framework\Module\Dir;
use Magento\Framework\Module\Dir\Reader;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Config provider for Bold Checkout.
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
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var Reader
     */
    private $moduleReader;

    /**
     * @var ReadFactory
     */
    private $readFactory;

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
     * @var ModuleConfig
     */
    private ModuleConfig $moduleConfig;

    /**
     * @param Session $checkoutSession
     * @param ConfigInterface $config
     * @param ClientInterface $client
     * @param AllowedCountries $allowedCountries
     * @param CollectionFactory $collectionFactory
     * @param Json $json
     * @param Reader $moduleReader
     * @param ReadFactory $readFactory
     * @param ModuleConfig $moduleConfig
     */
    public function __construct(
        Session $checkoutSession,
        ConfigInterface $config,
        ClientInterface $client,
        AllowedCountries $allowedCountries,
        CollectionFactory $collectionFactory,
        Json $json,
        Reader $moduleReader,
        ReadFactory $readFactory,
        ModuleConfig $moduleConfig
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->client = $client;
        $this->config = $config;
        $this->json = $json;
        $this->moduleReader = $moduleReader;
        $this->readFactory = $readFactory;
        $this->allowedCountries = $allowedCountries;
        $this->collectionFactory = $collectionFactory;
        $this->moduleConfig = $moduleConfig;
    }

    /**
     * @inheritdoc
     */
    public function getConfig(): array
    {
        $boldCheckoutData = $this->checkoutSession->getBoldCheckoutData();
        $quote = $this->checkoutSession->getQuote();
        $websiteId = (int)$quote->getStore()->getWebsiteId();

        if (!$boldCheckoutData || !$this->moduleConfig->isPaymentBoosterEnabled($websiteId)) {
            return [];
        }

        $shopId = $this->config->getShopId($websiteId);
        $publicOrderId = $boldCheckoutData['data']['public_order_id'] ?? null;
        $jwtToken = $boldCheckoutData['data']['jwt_token'] ?? null;

        return [
            'bold' => [
                'payment' => [
                    'iframeSrc' => $this->getIframeSrc($publicOrderId, $jwtToken, $websiteId),
                    'method' => Service::CODE,
                ],
                'shopId' => $shopId,
                'customerIsGuest' => $quote->getCustomerIsGuest(),
                'publicOrderId' => $publicOrderId,
                'jwtToken' => $jwtToken,
                'countries' => $this->getAllowedCountries(),
                'url' => $this->client->getUrl($websiteId, ''),
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

        try {
            $styles = $this->getStyles();
            if ($styles) {
                $this->client->post($websiteId, 'payments/styles', $styles);
            }
        } catch (\Exception $e) {
            return null;
        }

        return $this->client->getUrl($websiteId, 'payments/iframe?token=' . $jwtToken);
    }

    /**
     * Get iframe styles.
     *
     * @return array
     * @throws FileSystemException
     * @throws ValidatorException
     */
    private function getStyles(): array
    {
        $dir = $this->moduleReader->getModuleDir(Dir::MODULE_VIEW_DIR, 'Bold_CheckoutPaymentBooster');
        $read = $this->readFactory->create($dir . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'web');
        if (!$read->isFile('iframe-styles.json')) {
            return [];
        }

        return $this->json->unserialize($read->readFile('iframe-styles.json'));
    }

    /**
     * Get allowed countries.
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
