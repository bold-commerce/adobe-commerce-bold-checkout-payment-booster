<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Eps;

use Bold\CheckoutPaymentBooster\Model\Config;
use Bold\CheckoutPaymentBooster\Model\Http\Client\Command\GetCommand;

/**
 * Get fastlane styles from eps.
 */
class GetFastlaneStyles
{
    /**
     * @var GetCommand
     */
    private $getCommand;

    /**
     * @var Config
     */
    private $config;

    /**
     * @param GetCommand $getCommand
     * @param Config $config
     */
    public function __construct(GetCommand $getCommand, Config $config)
    {
        $this->getCommand = $getCommand;
        $this->config = $config;
    }

    /**
     * Get fastlane styles.
     *
     * @param int $websiteId
     * @return array
     */
    public function getStyles(int $websiteId): array
    {
        $configurationGroupLabel = $this->config->getConfigurationGroupLabel($websiteId);
        $epsStaticUrl = $this->config->getStaticEpsUrl($websiteId);
        $url = rtrim($epsStaticUrl, '/') . '/' . $configurationGroupLabel . '/custom-style.css';
        $result = $this->getCommand->execute($websiteId, $url, [])->getBody();
        return $result['fastlane']['styles'] ?? [];
    }
}
