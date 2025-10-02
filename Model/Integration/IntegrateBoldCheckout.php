<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Integration;

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
class IntegrateBoldCheckout
{
    private const INTEGRATION_URL = 'checkout/shop/{{shopId}}/api_config';

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
     * Update (or register) shared secret with Bold Checkout API Integration.
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
            'api_url' => $this->storeManager->getStore($storeId)->getBaseUrl(UrlInterface::URL_TYPE_WEB) . 'rest/V1',
            'api_key' => $sharedSecret,
        ];

        $result = $this->boldClient->post($websiteId, self::INTEGRATION_URL, $body);

        if ($result->getStatus() === 422 && $result->getErrors()) {
            $message = isset(current($result->getErrors())['message'])
                ? __(current($result->getErrors())['message'])
                : __('Failed to configure integration.');
            throw new LocalizedException($message);
        } elseif ($result->getStatus() !== 200) {
            $message = __('Failed to configure integration.');
            throw new LocalizedException($message);
        }
    }
}
