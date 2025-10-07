<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model;

use Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\BoldConfigInterface;
use Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\DiagnosticDataInterface;
use Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\PlatformInterface;
use Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\StoreInfoInterface;
use Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\ValidationsInterface;
use Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\WebsiteConfigurationInterface;
use Bold\CheckoutPaymentBooster\Model\Data\Diagnostic\BoldConfigFactory;
use Bold\CheckoutPaymentBooster\Model\Data\Diagnostic\DiagnosticDataFactory;
use Bold\CheckoutPaymentBooster\Model\Data\Diagnostic\PlatformFactory;
use Bold\CheckoutPaymentBooster\Model\Data\Diagnostic\StoreInfoFactory;
use Bold\CheckoutPaymentBooster\Model\Data\Diagnostic\ValidationsFactory;
use Bold\CheckoutPaymentBooster\Model\Data\Diagnostic\WebsiteConfigurationFactory;
use Exception;
use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Diagnostic Service Implementation
 *
 * Collects comprehensive diagnostic information about the Bold CheckoutPaymentBooster module
 * and returns it in the required JSON format.
 */
class DiagnosticService
{
    /** @var BoldConfigFactory */
    private $boldConfigFactory;

    /** @var Config */
    private $config;


    /** @var DiagnosticDataFactory */
    private $diagnosticDataFactory;

    /** @var DirectoryHelper */
    private $directoryHelper;

    /** @var DirectoryList */
    private $directoryList;

    /** @var File */
    private $fileDriver;

    /** @var JsonSerializer */
    private $jsonSerializer;

    /** @var LoggerInterface */
    private $logger;

    /** @var PlatformFactory */
    private $platformFactory;

    /** @var ProductMetadataInterface */
    private $productMetadata;

    /** @var ScopeConfigInterface */
    private $scopeConfig;

    /** @var StoreInfoFactory */
    private $storeInfoFactory;

    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var ValidationsFactory */
    private $validationsFactory;

    /** @var WebsiteConfigurationFactory */
    private $websiteConfigurationFactory;

    /**
     * @param BoldConfigFactory $boldConfigFactory
     * @param Config $config
     * @param DiagnosticDataFactory $diagnosticDataFactory
     * @param DirectoryHelper $directoryHelper
     * @param DirectoryList $directoryList
     * @param File $fileDriver
     * @param JsonSerializer $jsonSerializer
     * @param LoggerInterface $logger
     * @param PlatformFactory $platformFactory
     * @param ProductMetadataInterface $productMetadata
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreInfoFactory $storeInfoFactory
     * @param StoreManagerInterface $storeManager
     * @param ValidationsFactory $validationsFactory
     * @param WebsiteConfigurationFactory $websiteConfigurationFactory
     */
    public function __construct(
        BoldConfigFactory $boldConfigFactory,
        Config $config,
        DiagnosticDataFactory $diagnosticDataFactory,
        DirectoryHelper $directoryHelper,
        DirectoryList $directoryList,
        File $fileDriver,
        JsonSerializer $jsonSerializer,
        LoggerInterface $logger,
        PlatformFactory $platformFactory,
        ProductMetadataInterface $productMetadata,
        ScopeConfigInterface $scopeConfig,
        StoreInfoFactory $storeInfoFactory,
        StoreManagerInterface $storeManager,
        ValidationsFactory $validationsFactory,
        WebsiteConfigurationFactory $websiteConfigurationFactory
    ) {
        $this->boldConfigFactory = $boldConfigFactory;
        $this->config = $config;
        $this->diagnosticDataFactory = $diagnosticDataFactory;
        $this->directoryHelper = $directoryHelper;
        $this->directoryList = $directoryList;
        $this->fileDriver = $fileDriver;
        $this->jsonSerializer = $jsonSerializer;
        $this->logger = $logger;
        $this->platformFactory = $platformFactory;
        $this->productMetadata = $productMetadata;
        $this->scopeConfig = $scopeConfig;
        $this->storeInfoFactory = $storeInfoFactory;
        $this->storeManager = $storeManager;
        $this->validationsFactory = $validationsFactory;
        $this->websiteConfigurationFactory = $websiteConfigurationFactory;
    }

    /**
     * Get comprehensive diagnostic information
     *
     * @return DiagnosticDataInterface Diagnostic data in the required JSON format
     * @throws FileSystemException
     */
    public function getDiagnosticData(): DiagnosticDataInterface
    {
        try {
            $timestamp = date('Y-m-d H:i:s');

            $platform = $this->getPlatform();
            $storeInfo = $this->getStoreInfo();
            $boldConfig = $this->getBoldConfig();

            /** @var DiagnosticDataInterface $result */
            $result = $this->diagnosticDataFactory->create();
            $result->setSuccess(true)
                   ->setError(null)
                   ->setTimestamp($timestamp)
                   ->setPlatform($platform)
                   ->setStoreInfo($storeInfo)
                   ->setBoldConfig($boldConfig);

            return $result;
        } catch (Exception $e) {
            $this->logger->error('Diagnostic data collection failed: ' . $e->getMessage(), [
                'exception' => $e
            ]);

            /** @var DiagnosticDataInterface $errorResult */
            $errorResult = $this->diagnosticDataFactory->create();
            $errorResult->setSuccess(false)
                        ->setError($e->getMessage())
                        ->setTimestamp(date('Y-m-d H:i:s'))
                        ->setPlatform($this->getPlatform());

            return $errorResult;
        }
    }

    /**
     * Determines the installation path of the module based on the existence of specific files.
     *
     * @return string Returns 'vendor' if the module is installed in the vendor directory,
     *                otherwise 'app/code'.
     * @throws FileSystemException
     */
    public function getInstallPath(): string
    {
        $baseDir = $this->directoryList->getRoot();

        $vendorPath = $baseDir . '/vendor/bold-commerce/module-checkout-payment-booster';
        if ($this->fileDriver->isExists($vendorPath . '/composer.json')) {
            return 'vendor';
        }

        return 'app/code';
    }

    /**
     * Get Bold configuration
     */
    private function getBoldConfig(): BoldConfigInterface
    {
        try {
            $websiteId = (int) $this->storeManager->getWebsite()->getId();

            // Create validations object
            $validations = $this->getValidations($websiteId);

            /** @var BoldConfigInterface $boldConfig */
            $boldConfig = $this->boldConfigFactory->create();
            $boldConfig->setShopId($this->config->getShopId($websiteId) ?? '')
                      ->setBoldBoosterFlowId($this->config->getBoldBoosterFlowID($websiteId) ?? '')
                      ->setConfigurationGroupLabel($this->config->getConfigurationGroupLabel($websiteId))
                      ->setIsCartWalletPayEnabled($this->config->isCartWalletPayEnabled($websiteId))
                      ->setIsExpressPayEnabled($this->config->isExpressPayEnabled($websiteId))
                      ->setIsFastlaneEnabled($this->config->isFastlaneEnabled($websiteId))
                      ->setIsPaymentBoosterEnabled($this->config->isPaymentBoosterEnabled($websiteId))
                      ->setIsProductWalletPayEnabled($this->config->isProductWalletPayEnabled($websiteId))
                      ->setStaticEpsUrl($this->config->getStaticEpsUrl($websiteId) ?? '')
                      ->setLogEnabled($this->config->getLogIsEnabled($websiteId))
                      ->setEpsUrl($this->config->getEpsUrl($websiteId) ?? '')
                      ->setApiUrl($this->config->getApiUrl($websiteId) ?? '')
                      ->setFastlanePaymentTitle($this->scopeConfig->getValue(
                          'checkout/bold_checkout_payment_booster/fastlane_payment_title',
                          ScopeInterface::SCOPE_WEBSITE,
                          $websiteId
                      ) ?? '')
                      ->setWalletPaymentTitle($this->scopeConfig->getValue(
                          'checkout/bold_checkout_payment_booster/wallet_payment_title',
                          ScopeInterface::SCOPE_WEBSITE,
                          $websiteId
                      ) ?? '')
                      ->setPaymentTitle($this->scopeConfig->getValue(
                          'checkout/bold_checkout_payment_booster/payment_title',
                          ScopeInterface::SCOPE_WEBSITE,
                          $websiteId
                      ) ?? '')
                      ->setEnableSalesOrderViewTab($this->config->isShowSalesOrderViewTab($websiteId))
                      ->setValidations($validations);

            return $boldConfig;
        } catch (Exception $e) {
            // Create default validations object
            /** @var ValidationsInterface $defaultValidations */
            $defaultValidations = $this->validationsFactory->create();
            $defaultValidations->setConfigured(false)
                              ->setApiUrlConfigured(false)
                              ->setShopIdConfigured(false)
                              ->setStaticEpsConfigured(false)
                              ->setEpsConfigured(false)
                              ->setTestRequestSuccessful(false);

            /** @var BoldConfigInterface $boldConfig */
            $boldConfig = $this->boldConfigFactory->create();
            $boldConfig->setShopId('')
                      ->setBoldBoosterFlowId('')
                      ->setConfigurationGroupLabel(null)
                      ->setIsCartWalletPayEnabled(false)
                      ->setIsExpressPayEnabled(false)
                      ->setIsFastlaneEnabled(false)
                      ->setIsPaymentBoosterEnabled(false)
                      ->setIsProductWalletPayEnabled(false)
                      ->setStaticEpsUrl('')
                      ->setLogEnabled(false)
                      ->setEpsUrl('')
                      ->setApiUrl('')
                      ->setFastlanePaymentTitle('')
                      ->setWalletPaymentTitle('')
                      ->setPaymentTitle('')
                      ->setEnableSalesOrderViewTab(false)
                      ->setValidations($defaultValidations);

            return $boldConfig;
        }
    }


    /**
     * Retrieve the version of the Bold CheckoutPaymentBooster module
     *
     * @return string Returns the module version if found, or 'Bold CheckoutPaymentBooster N/A'
     *
     * if not found or on failure
     *
     * @throws FileSystemException
     */
    private function getModuleVersion(): string
    {
        $baseDir = $this->directoryList->getRoot();

        $pathsToCheck = [
            $baseDir . '/vendor/bold-commerce/module-checkout-payment-booster',
            $baseDir . '/app/code/Bold/CheckoutPaymentBooster'
        ];

        foreach ($pathsToCheck as $path) {
            $composerJsonPath = $path . '/composer.json';
            $composerData = $this->parseJsonFile($composerJsonPath);

            if ($composerData && isset($composerData['version'])) {
                return 'Bold CheckoutPaymentBooster ' . $composerData['version'];
            }
        }

        return 'Bold CheckoutPaymentBooster N/A';
    }

    /**
     * Get platform information
     *
     * @throws FileSystemException
     */
    private function getPlatform(): PlatformInterface
    {
        /** @var PlatformInterface $platform */
        $platform = $this->platformFactory->create();
        $platform->setPlatformVersion(
            $this->productMetadata->getName() . ' ' .
            $this->productMetadata->getEdition() . ' ' .
            $this->productMetadata->getVersion()
        )
        ->setVersion($this->getModuleVersion())
        ->setInstallPath($this->getInstallPath());

        return $platform;
    }

    /**
     * Get store information
     *
     * @return array<int, StoreInfoInterface>
     */
    private function getStoreInfo(): array
    {
        try {
            $stores = $this->storeManager->getStores(true);
            $storeInfoArray = [];

            foreach ($stores as $store) {
                if ($store->getId() == 0) {
                    continue; // Skip admin store
                }

                $website = $this->storeManager->getWebsite($store->getWebsiteId());
                $countryCode = $this->directoryHelper->getDefaultCountry($store->getId());

                // Create website configuration object
                /** @var WebsiteConfigurationInterface $websiteConfig */
                $websiteConfig = $this->websiteConfigurationFactory->create();
                $websiteConfig->setCountry($countryCode)
                             ->setCurrency($this->scopeConfig->getValue(
                                 'currency/options/base',
                                 ScopeInterface::SCOPE_STORE,
                                 $store->getId()
                             ))
                             ->setLocale($this->scopeConfig->getValue(
                                 'general/locale/code',
                                 ScopeInterface::SCOPE_STORE,
                                 $store->getId()
                             ))
                             ->setTimezone($this->scopeConfig->getValue(
                                 'general/locale/timezone',
                                 ScopeInterface::SCOPE_STORE,
                                 $store->getId()
                             ))
                             ->setIsSingleStore($this->storeManager->isSingleStoreMode())
                             ->setBaseUrl($store->getBaseUrl());

                // Create store info object
                /** @var StoreInfoInterface $storeInfo */
                $storeInfo = $this->storeInfoFactory->create();
                $storeInfo->setWebsiteId((int) $website->getId())
                         ->setStoreId((string) $store->getId())
                         ->setStoreName($store->getName())
                         ->setWebsiteConfiguration($websiteConfig);

                $storeInfoArray[] = $storeInfo;
            }

            return $storeInfoArray;
        } catch (Exception $e) {
            return [];
        }
    }


    /**
     * Get validation status for Bold configuration
     *
     * @param int $websiteId
     * @return ValidationsInterface
     */
    private function getValidations(int $websiteId): ValidationsInterface
    {
        $shopId = $this->config->getShopId($websiteId);
        $apiUrl = $this->config->getApiUrl($websiteId);
        $staticEpsUrl = $this->config->getStaticEpsUrl($websiteId);
        $epsUrl = $this->config->getEpsUrl($websiteId);

        /** @var ValidationsInterface $validations */
        $validations = $this->validationsFactory->create();
        $validations->setConfigured(!empty($shopId) && !empty($apiUrl))
                   ->setApiUrlConfigured(!empty($apiUrl))
                   ->setShopIdConfigured(!empty($shopId))
                   ->setStaticEpsConfigured(!empty($staticEpsUrl))
                   ->setEpsConfigured(!empty($epsUrl))
                   ->setTestRequestSuccessful($this->testApiConnection($apiUrl));

        return $validations;
    }

    /**
     * Parse JSON file using Magento's JSON serializer
     *
     * @param string $jsonPath
     * @return array<string, mixed>|null
     * @throws FileSystemException
     */
    private function parseJsonFile(string $jsonPath): ?array
    {
        if (!$this->fileDriver->isExists($jsonPath)) {
            return null;
        }

        try {
            $jsonContent = $this->fileDriver->fileGetContents($jsonPath);
            $result = $this->jsonSerializer->unserialize($jsonContent);
            return is_array($result) ? $result : null;
        } catch (Exception $e) {
            $this->logger->error("Error parsing JSON file " . $jsonPath . ": " . $e->getMessage());
            return null;
        }
    }


    /**
     * Test API connection (simplified check)
     *
     * @param string|null $apiUrl
     * @return bool
     */
    private function testApiConnection(?string $apiUrl): bool
    {
        if (empty($apiUrl)) {
            return false;
        }
        return filter_var($apiUrl, FILTER_VALIDATE_URL) !== false;
    }

}
