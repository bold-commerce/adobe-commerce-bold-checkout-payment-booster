<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Modules;

use Bold\CheckoutPaymentBooster\Api\Data\Module\ResultInterface;
use Bold\CheckoutPaymentBooster\Api\Data\Module\ResultInterfaceFactory;
use Bold\CheckoutPaymentBooster\Api\GetModulesInfoInterface;
use Bold\CheckoutPaymentBooster\Model\Config;
use Bold\CheckoutPaymentBooster\Model\Modules\GetModulesInfo\GetModuleInfo;
use Exception;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

use function sprintf;

/**
 * @inheritDoc
 */
class GetModulesInfo implements GetModulesInfoInterface
{
    /**
     * @var ComponentRegistrar
     */
    private $componentRegistrar;

    /**
     * @var ResultInterfaceFactory
     */
    private $resultFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var GetModuleInfo
     */
    private $getModuleInfo;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param ComponentRegistrar $componentRegistrar
     * @param ResultInterfaceFactory $resultFactory
     * @param GetModuleInfo $getModuleInfo
     * @param LoggerInterface $logger
     */
    public function __construct(
        ComponentRegistrar $componentRegistrar,
        ResultInterfaceFactory $resultFactory,
        GetModuleInfo $getModuleInfo,
        Config $config,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger
    ) {
        $this->componentRegistrar = $componentRegistrar;
        $this->resultFactory = $resultFactory;
        $this->logger = $logger;
        $this->getModuleInfo = $getModuleInfo;
        $this->config = $config;
        $this->storeManager = $storeManager;
    }

    /**
     * @inheritDoc
     */
    public function getModulesInfo(string $shopId): ResultInterface
    {
        $shopIdFound = false;
        foreach ($this->storeManager->getWebsites(false) as $website) {
            if ($this->config->getShopId((int)$website->getId()) === $shopId) {
                $shopIdFound = true;
                break;
            }
        }
        if (!$shopIdFound) {
            throw new LocalizedException(__('Wrong shop ID provided.'));
        }
        $modulesInfo = [];
        $moduleList = $this->getInstalledBoldModules();
        foreach ($moduleList as $moduleName) {
            try {
                $modulesInfo[] = $this->getModuleInfo->getInfo($moduleName);
            } catch (Exception $e) {
                $this->logger->error(sprintf('Cannot get "%s" info: %s', $moduleName, $e->getMessage()));
            }
        }
        return $this->resultFactory->create(['modules' => $modulesInfo]);
    }

    /**
     * Retrieve installed Bold modules names via component registrar.
     *
     * @return array
     */
    private function getInstalledBoldModules(): array
    {
        $boldModules = [];
        $paths = $this->componentRegistrar->getPaths(ComponentRegistrar::MODULE);
        $moduleNames = array_keys($paths);
        foreach ($moduleNames as $moduleName) {
            if (strpos($moduleName, 'Bold_') === 0) {
                $boldModules[] = $moduleName;
            }
        }
        return $boldModules;
    }
}
