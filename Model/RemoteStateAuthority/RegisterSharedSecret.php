<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\RemoteStateAuthority;

use Bold\CheckoutPaymentBooster\Model\Http\BoldClient;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\Website;

/**
 * Shared secret registration service
 */
class RegisterSharedSecret
{
    private const REGISTER_URL = 'checkout/shop/{{shopId}}/rsa_config';

    // 'Remote State Authority not configured' error code.
    private const CODE_RSA_NOT_CONFIGURED = '02-89';

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
     * Update (or register) shared secret.
     *
     * @param int $websiteId
     * @param string $sharedSecret
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute(int $websiteId, string $sharedSecret): void
    {
        /** @var WebsiteInterface&Website $website */
        $website = $this->storeManager->getWebsite($websiteId);
        $storeId = $website->getDefaultStore()->getId();
        $body = [
            'url' => $this->storeManager->getStore($storeId)->getBaseUrl(UrlInterface::URL_TYPE_WEB) . 'rest/V1',
            'shared_secret' => $sharedSecret,
        ];
        $result = $this->boldClient->patch($websiteId, self::REGISTER_URL, $body);
        if ($result->getErrors()
            && isset($result->getErrors()[0]['code'])
            && $result->getErrors()[0]['code'] === self::CODE_RSA_NOT_CONFIGURED
        ) {
            $result = $this->boldClient->post($websiteId, self::REGISTER_URL, $body);
        }
        if ($result->getErrors()) {
            $message = isset(current($result->getErrors())['message'])
                ? __(current($result->getErrors())['message'])
                : __('Remote State Authority registration failed.');
            throw new LocalizedException($message);
        }
    }
}
