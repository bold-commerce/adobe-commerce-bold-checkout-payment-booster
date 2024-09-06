<?php

namespace Bold\CheckoutPaymentBooster\Block\System\Config\Form\Field;

use Bold\CheckoutPaymentBooster\Model\Config as ModuleConfig;
use Bold\CheckoutPaymentBooster\Model\Http\Client\RequestsLogger;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\HTTP\ClientInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Util\Exception;

class OnboardBanner extends Field
{
    const ONBOARD_IN_PROGRESS_DATA_PATH = 'https://apps.boldapps.net/onboarding_banner/adobe-commerce/payment-booster/in_progress';
    const ONBOARD_COMPLETED_DATA_PATH = 'https://apps.boldapps.net/onboarding_banner/adobe-commerce/payment-booster/complete';

    /** @var StoreManagerInterface */
    private $storeManager;

    /**
     * @var ModuleConfig
     */
    private $moduleConfig;

    /** @var ClientInterface */
    private $client;

    /** @var RequestsLogger */
    private $logger;

    /** @var string */
    protected $_template = 'Bold_CheckoutPaymentBooster::system/config/form/field/onboard_banner.phtml';

    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        ModuleConfig $moduleConfig,
        ClientInterface $client,
        RequestsLogger $logger
    ) {
        parent::__construct($context, []);
        $this->storeManager = $storeManager;
        $this->moduleConfig = $moduleConfig;
        $this->client = $client;
        $this->logger = $logger;
    }

    /**
     * Render element HTML
     *
     * @param  AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element)
    {
        return $this->toHtml();
    }

    /**
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getBannerData()
    {
        $websiteId = $this->storeManager->getWebsite()->getId();
        $bannerDataUrl = $this->isOnboardComplete() ? self::ONBOARD_COMPLETED_DATA_PATH : self::ONBOARD_IN_PROGRESS_DATA_PATH;

        $this->client->get($bannerDataUrl);

        if ($this->client->getStatus() !== 200) {
            $this->logger->logRequest($websiteId, $bannerDataUrl, 'GET');
            return null;
        }

        return json_decode($this->client->getBody());
    }

    public function isOnboardComplete()
    {
        $websiteId = $this->storeManager->getWebsite()->getId();
        return $this->moduleConfig->isPaymentBoosterEnabled($websiteId);
    }
}
