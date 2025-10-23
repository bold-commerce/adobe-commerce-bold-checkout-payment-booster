<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model;

use Exception;
use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Simple Diagnostic Service
 *
 * A simplified version of the diagnostic service that generates JSON data
 * for sending to external APIs without complex interfaces.
 */
class SimpleDiagnosticService
{
    private const DIAGNOSTIC_FILE_NAME = 'bold_diagnostic_data.json';
    private const MODULE_NAME = 'Bold_CheckoutPaymentBooster';

    /** @var Config  */
    private $config;
    /** @var DirectoryHelper  */
    private $directoryHelper;
    /** @var DirectoryList  */
    private $directoryList;
    /** @var File  */
    private $fileDriver;
    /** @var JsonSerializer  */
    private $jsonSerializer;
    /** @var LoggerInterface  */
    private $logger;
    /** @var ModuleListInterface  */
    private $moduleList;
    /** @var ProductMetadataInterface  */
    private $productMetadata;
    /** @var ScopeConfigInterface  */
    private $scopeConfig;
    /** @var StoreManagerInterface  */
    private $storeManager;

    /**
     * @param Config $config
     * @param DirectoryHelper $directoryHelper
     * @param DirectoryList $directoryList
     * @param File $fileDriver
     * @param JsonSerializer $jsonSerializer
     * @param LoggerInterface $logger
     * @param ModuleListInterface $moduleList
     * @param ProductMetadataInterface $productMetadata
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Config $config,
        DirectoryHelper $directoryHelper,
        DirectoryList $directoryList,
        File $fileDriver,
        JsonSerializer $jsonSerializer,
        LoggerInterface $logger,
        ModuleListInterface $moduleList,
        ProductMetadataInterface $productMetadata,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager
    ) {
        $this->config = $config;
        $this->directoryHelper = $directoryHelper;
        $this->directoryList = $directoryList;
        $this->fileDriver = $fileDriver;
        $this->jsonSerializer = $jsonSerializer;
        $this->logger = $logger;
        $this->moduleList = $moduleList;
        $this->productMetadata = $productMetadata;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
    }

    /**
     * Generate diagnostic data as array
     *
     * @return array<string, mixed><string, mixed>
     */
    public function generateDiagnosticData(): array
    {
        try {
            $timestamp = date('Y-m-d H:i:s');

            return [
                'success' => true,
                'error' => null,
                'timestamp' => $timestamp,
                'platform' => $this->getPlatformInfo(),
                'store_info' => $this->getStoreInfo(),
                'bold_config' => $this->getBoldConfigInfo(),
                'module_info' => $this->getModuleInfo()
            ];
        } catch (Exception $e) {
            $this->logger->error('Diagnostic data generation failed: ' . $e->getMessage(), [
                'exception' => $e
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s'),
                'platform' => $this->getPlatformInfo()
            ];
        }
    }

    /**
     * Generate diagnostic data and save to JSON file
     *
     * @param string|null $filePath Optional custom file path
     * @return array<string, mixed><string, mixed> Result with success status and file path
     */
    public function generateAndSaveDiagnosticData(?string $filePath = null): array
    {
        try {
            $diagnosticData = $this->generateDiagnosticData();
            $jsonData = $this->jsonSerializer->serialize($diagnosticData);

            if ($filePath === null) {
                $filePath = $this->getDefaultFilePath();
            }

            if (is_string($jsonData)) {
                $this->fileDriver->filePutContents($filePath, $jsonData);
            }

            $this->logger->info('Diagnostic data saved successfully', [
                'file_path' => $filePath,
                'data_size' => is_string($jsonData) ? strlen($jsonData) : 0
            ]);

            return [
                'success' => true,
                'message' => 'Diagnostic data generated and saved successfully',
                'file_path' => $filePath,
                'data' => $diagnosticData
            ];
        } catch (Exception $e) {
            $this->logger->error('Failed to save diagnostic data: ' . $e->getMessage(), [
                'exception' => $e,
                'file_path' => $filePath
            ]);

            return [
                'success' => false,
                'message' => 'Failed to save diagnostic data: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get diagnostic data as JSON string
     *
     * @return string
     */
    public function getDiagnosticDataAsJson(): string
    {
        $diagnosticData = $this->generateDiagnosticData();
        $result = $this->jsonSerializer->serialize($diagnosticData);
        return is_string($result) ? $result : '';
    }

    /**
     * Get platform information
     *
     * @return array<string, mixed>
     */
    private function getPlatformInfo(): array
    {
        try {
            return [
                'platform_version' => $this->productMetadata->getName() . ' ' .
                    $this->productMetadata->getEdition() . ' ' .
                    $this->productMetadata->getVersion(),
                'version' => $this->getModuleVersion(),
                'install_path' => $this->getInstallPath(),
                'php_version' => PHP_VERSION,
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
            ];
        } catch (Exception $e) {
            $this->logger->error('Failed to get platform info: ' . $e->getMessage());
            return [
                'platform_version' => 'Unknown',
                'module_version' => 'Unknown',
                'install_path' => 'Unknown',
                'php_version' => PHP_VERSION,
                'server_software' => 'Unknown'
            ];
        }
    }

    /**
     * Get store information
     *
     * @return array<int, array<string, mixed>>
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

                $storeInfoArray[] = [
                    'website_id' => (int) $website->getId(),
                    'store_id' => (string) $store->getId(),
                    'store_name' => $store->getName(),
                    'website_configuration' => [
                        'country' => $countryCode,
                        'currency' => $this->scopeConfig->getValue(
                            'currency/options/base',
                            ScopeInterface::SCOPE_STORE,
                            $store->getId()
                        ),
                        'locale' => $this->scopeConfig->getValue(
                            'general/locale/code',
                            ScopeInterface::SCOPE_STORE,
                            $store->getId()
                        ),
                        'timezone' => $this->scopeConfig->getValue(
                            'general/locale/timezone',
                            ScopeInterface::SCOPE_STORE,
                            $store->getId()
                        ),
                        'is_single_store' => $this->storeManager->isSingleStoreMode(),
                        'base_url' => $store->getBaseUrl()
                    ]
                ];
            }

            return $storeInfoArray;
        } catch (Exception $e) {
            $this->logger->error('Failed to get store info: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get Bold configuration information
     *
     * @return array<string, mixed>
     */
    private function getBoldConfigInfo(): array
    {
        try {
            $websiteId = (int) $this->storeManager->getWebsite()->getId();

            return [
                'shop_id' => $this->config->getShopId($websiteId) ?? '',
                'bold_booster_flow_id' => $this->config->getBoldBoosterFlowID($websiteId) ?? '',
                'configuration_group_label' => $this->config->getConfigurationGroupLabel($websiteId),
                'is_cart_wallet_pay_enabled' => $this->config->isCartWalletPayEnabled($websiteId),
                'is_express_pay_enabled' => $this->config->isExpressPayEnabled($websiteId),
                'is_fastlane_enabled' => $this->config->isFastlaneEnabled($websiteId),
                'is_payment_booster_enabled' => $this->config->isPaymentBoosterEnabled($websiteId),
                'is_product_wallet_pay_enabled' => $this->config->isProductWalletPayEnabled($websiteId),
                'static_eps_url' => $this->config->getStaticEpsUrl($websiteId) ?? '',
                'log_enabled' => $this->config->getLogIsEnabled($websiteId),
                'eps_url' => $this->config->getEpsUrl($websiteId) ?? '',
                'api_url' => $this->config->getApiUrl($websiteId) ?? '',
                'fastlane_payment_title' => $this->scopeConfig->getValue(
                    'checkout/bold_checkout_payment_booster/fastlane_payment_title',
                    ScopeInterface::SCOPE_WEBSITE,
                    $websiteId
                ) ?? '',
                'wallet_payment_title' => $this->scopeConfig->getValue(
                    'checkout/bold_checkout_payment_booster/wallet_payment_title',
                    ScopeInterface::SCOPE_WEBSITE,
                    $websiteId
                ) ?? '',
                'payment_title' => $this->scopeConfig->getValue(
                    'checkout/bold_checkout_payment_booster/payment_title',
                    ScopeInterface::SCOPE_WEBSITE,
                    $websiteId
                ) ?? '',
                'enable_sales_order_view_tab' => $this->config->isShowSalesOrderViewTab($websiteId),
                'validations' => $this->getValidationsInfo($websiteId)
            ];
        } catch (Exception $e) {
            $this->logger->error('Failed to get Bold config info: ' . $e->getMessage());
            return [
                'shop_id' => '',
                'bold_booster_flow_id' => '',
                'configuration_group_label' => null,
                'is_cart_wallet_pay_enabled' => false,
                'is_express_pay_enabled' => false,
                'is_fastlane_enabled' => false,
                'is_payment_booster_enabled' => false,
                'is_product_wallet_pay_enabled' => false,
                'static_eps_url' => '',
                'log_enabled' => false,
                'eps_url' => '',
                'api_url' => '',
                'fastlane_payment_title' => '',
                'wallet_payment_title' => '',
                'payment_title' => '',
                'enable_sales_order_view_tab' => false,
                'validations' => $this->getDefaultValidationsInfo()
            ];
        }
    }

    /**
     * Get module information
     *
     * @return array<string, mixed>
     */
    private function getModuleInfo(): array
    {
        try {
            $module = $this->moduleList->getOne(self::MODULE_NAME);
            $installPath = $this->getInstallPath();
            
            // Extract version from the existing platform info
            $platformInfo = $this->getPlatformInfo();
            $moduleVersionString = $platformInfo['version'] ?? 'Bold CheckoutPaymentBooster N/A';
            
            // Extract just the version number (remove "Bold CheckoutPaymentBooster " prefix)
            $version = 'N/A';
            if (strpos($moduleVersionString, 'Bold CheckoutPaymentBooster ') === 0) {
                $version = substr($moduleVersionString, strlen('Bold CheckoutPaymentBooster '));
            }
            
            return [
                'name' => self::MODULE_NAME,
                'version' => $version,
                'is_enabled' => $module !== null,
                'install_path' => $installPath
            ];
        } catch (Exception $e) {
            $this->logger->error('Failed to get module info: ' . $e->getMessage());
            return [
                'name' => self::MODULE_NAME,
                'version' => 'N/A',
                'is_enabled' => false,
                'install_path' => 'Unknown'
            ];
        }
    }

    /**
     * Get validation status for Bold configuration
     *
     * @param int $websiteId
     * @return array<string, mixed>
     */
    private function getValidationsInfo(int $websiteId): array
    {
        try {
            $shopId = $this->config->getShopId($websiteId);
            $apiUrl = $this->config->getApiUrl($websiteId);
            $staticEpsUrl = $this->config->getStaticEpsUrl($websiteId);
            $epsUrl = $this->config->getEpsUrl($websiteId);

            return [
                'configured' => !empty($shopId) && !empty($apiUrl),
                'api_url_configured' => !empty($apiUrl),
                'shop_id_configured' => !empty($shopId),
                'static_eps_configured' => !empty($staticEpsUrl),
                'eps_configured' => !empty($epsUrl),
                'test_request_successful' => $this->testApiConnection($apiUrl)
            ];
        } catch (Exception $e) {
            $this->logger->error('Failed to get validations info: ' . $e->getMessage());
            return $this->getDefaultValidationsInfo();
        }
    }

    /**
     * Get default validation info
     *
     * @return array<string, mixed>
     */
    private function getDefaultValidationsInfo(): array
    {
        return [
            'configured' => false,
            'api_url_configured' => false,
            'shop_id_configured' => false,
            'static_eps_configured' => false,
            'eps_configured' => false,
            'test_request_successful' => false
        ];
    }

    /**
     * Get module version
     *
     * @return string
     */
    private function getModuleVersion(): string
    {
        try {
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
        } catch (Exception $e) {
            $this->logger->error('Failed to get module version: ' . $e->getMessage());
            return 'Bold CheckoutPaymentBooster N/A';
        }
    }

    /**
     * Get installation path
     *
     * @return string
     */
    public function getInstallPath(): string
    {
        try {
            $baseDir = $this->directoryList->getRoot();
            $vendorPath = $baseDir . '/vendor/bold-commerce/module-checkout-payment-booster';

            if ($this->fileDriver->isExists($vendorPath . '/composer.json')) {
                return 'vendor';
            }

            return 'app/code';
        } catch (Exception $e) {
            $this->logger->error('Failed to get install path: ' . $e->getMessage());
            return 'Unknown';
        }
    }

    /**
     * Get default file path for diagnostic data
     *
     * @return string
     * @throws FileSystemException
     */
    private function getDefaultFilePath(): string
    {
        $varDir = $this->directoryList->getPath(DirectoryList::VAR_DIR);
        return $varDir . '/' . self::DIAGNOSTIC_FILE_NAME;
    }

    /**
     * Parse JSON file
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
            $this->logger->error("Error parsing JSON file {$jsonPath}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Test API connection
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
