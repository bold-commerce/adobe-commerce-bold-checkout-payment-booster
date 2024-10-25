<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\RemoteStateAuthority;

use Bold\CheckoutPaymentBooster\Model\Http\BoldClient;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Shared secret registration service
 */
class RegisterSharedSecret
{
    private const RSA_CONFIG_PATH = 'checkout/shop/{{shopId}}/rsa_config';

    /**
     * @var BoldClient
     */
    private $boldClient;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param BoldClient $boldClient
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        BoldClient $boldClient,
        StoreManagerInterface $storeManager
    ) {
        $this->boldClient = $boldClient;
        $this->storeManager = $storeManager;
    }

    /**
     * Register generated shared secret and endpoint URL in RSA configuration.
     *
     * @param int $websiteId
     * @param string $sharedSecret
     * @return void
     * @throws LocalizedException
     */
    public function execute(int $websiteId, string $sharedSecret): void
    {
        $storeId = $this->storeManager->getWebsite($websiteId)->getDefaultStore()->getId();
        $body = [
            'url' => $this->storeManager->getStore($storeId)->getBaseUrl(UrlInterface::URL_TYPE_WEB) . 'rest/V1',
            'shared_secret' => $sharedSecret,
        ];
        $this->boldClient->delete($websiteId, self::RSA_CONFIG_PATH, []);
        $result = $this->boldClient->post($websiteId, self::RSA_CONFIG_PATH, $body);
        if ($result->getErrors()) {
            $message = isset(current($result->getErrors())['message'])
                ? __(current($result->getErrors())['message'])
                : __('Remote State Authority registration failed.');
            throw new LocalizedException($message);
        }
    }
}
