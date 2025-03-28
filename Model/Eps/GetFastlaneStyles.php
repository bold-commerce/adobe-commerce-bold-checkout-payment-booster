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
     * @param string $baseUrl
     * @return array{privacy: "yes"|"no", input: string[], root: string[]}
     */
    public function getStyles(int $websiteId, string $baseUrl): array
    {
        $configurationGroupLabel = $this->config->getConfigurationGroupLabel($websiteId);
        if (empty($configurationGroupLabel)) {
            $configurationGroupLabel = parse_url($baseUrl)['host'] ?? '';
        }

        $epsStaticUrl = $this->config->getStaticEpsUrl($websiteId);
        $url = rtrim($epsStaticUrl, '/') . '/' . $configurationGroupLabel . '/custom-style.css';
        $result = $this->getCommand->execute($websiteId, $url, [])->getBody();
        return $result['fastlane']['styles'] ?? [];
    }
}
