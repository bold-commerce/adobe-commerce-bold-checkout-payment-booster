<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Block\Adminhtml\System\Config\Form\Field;

use Exception;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Escaper;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Filesystem\Driver\File;
use Bold\CheckoutPaymentBooster\Model\SimpleDiagnosticService;

/**
 * Version Information Block
 */
class VersionInfo extends Field
{
    /**
     * Package name for Packagist
     */
    private const PACKAGE_NAME = 'bold-commerce/module-checkout-payment-booster';

    /**
     * Packagist V1 API endpoint
     */
    private const PACKAGIST_V1_API = 'https://packagist.org/packages/%s.json';

    /**
     * @var ModuleListInterface
     */
    private $moduleList;

    /**
     * @var SimpleDiagnosticService
     */
    private $diagnosticService;

    /**
     * @var Curl
     */
    private $curl;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var File
     */
    private $fileDriver;

    /**
     * @var Escaper
     */
    private $escaper;

    /**
     * @param Context $context
     * @param Curl $curl
     * @param Escaper $escaper
     * @param File $fileDriver
     * @param Json $json
     * @param ModuleListInterface $moduleList
     * @param SimpleDiagnosticService $diagnosticService
     * @param mixed[] $data
     */
    public function __construct(
        Context $context,
        Curl $curl,
        Escaper $escaper,
        File $fileDriver,
        Json $json,
        ModuleListInterface $moduleList,
        SimpleDiagnosticService $diagnosticService,
        array $data = []
    ) {
        $this->curl = $curl;
        $this->diagnosticService = $diagnosticService;
        $this->escaper = $escaper;
        $this->fileDriver = $fileDriver;
        $this->json = $json;
        $this->moduleList = $moduleList;
        parent::__construct($context, $data);
    }

    /**
     * Render version information
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        try {
            $moduleInfo = $this->moduleList->getOne('Bold_CheckoutPaymentBooster');
            $currentVersion = $moduleInfo['setup_version'] ?? null;

            $installPath = $this->diagnosticService->getInstallPath();

            // If version is not found in module list, try to get it from composer.json
            if (!$currentVersion) {
                $currentVersion = $this->getVersionFromComposer($installPath);
            }

            // Get latest version from Packagist
            $latestVersion = $this->getLatestVersionV1();

            // Check if update is available
            $isUpdateAvailable = $this->isUpdateAvailable($currentVersion, $latestVersion);
            $updateStatus = $isUpdateAvailable ?
                '<span class="status-available">⚠ ' . __('Update Available') . '</span>' :
                '<span class="status-up-to-date">✓ ' . __('Up to Date') . '</span>';

            $html = '<div class="diagnostic-version-info">';
            $html .= '<table class="diagnostic-version-info__table">';
            $escapedCurrentVersion = $this->escaper->escapeHtml((string) $currentVersion);
            $escapedLatestVersion = $this->escaper->escapeHtml(
                (string) ($latestVersion ?? __('Unable to fetch'))
            );

            $currentVersionValue = is_string($escapedCurrentVersion)
                ? $escapedCurrentVersion
                : (string) $currentVersion;

            $html .= '<tr><td class="diagnostic-version-info__label">'
                . __('Installed Version') . ':</td><td class="diagnostic-version-info__value">'
                . $currentVersionValue . '</td></tr>';

            $html .= '<tr><td class="diagnostic-version-info__label">' .
                __('Install Path') . ':</td><td class="diagnostic-version-info__value">' .
                ($installPath) . '</td></tr>';

            // Only show latest version if it's different from current version or if update is available
            if ($isUpdateAvailable && $latestVersion) {
                $latestVersionValue = is_string($escapedLatestVersion)
                    ? $escapedLatestVersion
                    : $latestVersion;

                $html .= '<tr><td class="diagnostic-version-info__label">' .
                    __('Latest Version') . ':</td><td class="diagnostic-version-info__value">' .
                    $latestVersionValue . '</td></tr>';
            }

            $html .= '<tr><td class="diagnostic-version-info__label">' .
                __('Update Status') . ':</td><td class="diagnostic-version-info__status">' .
                $updateStatus . '</td></tr>';
            $html .= '</table>';

            if ($isUpdateAvailable && $latestVersion) {
                $html .= '<div class="diagnostic-version-info__update-warning">';
                $html .= '<strong>' . __('Update Available') . ':</strong> ' .
                    __('A newer version (%1) is available.',
                        $this->escaper->escapeHtml($latestVersion)) . ' ';

                if ($installPath === 'app/code') {
                    $manualGuideUrl = 'https://developer.boldcommerce.com/guides/checkout/bold-boosters/' .
                        'adobe-commerce-bold-booster-for-paypal?config=manual';
                    $html .= __(
                        'Please follow the <a href="%1" target="_blank" ' .
                        'class="diagnostic-version-info__update-warning-link">manual installation guide</a> ' .
                        'to update the module.',
                        $manualGuideUrl
                    );
                } else {
                    $html .= __('Please update via Composer: <code>' . self::PACKAGE_NAME . '</code>');
                }

                $html .= '</div>';
            }

            $html .= '</div>';

            return $html;
        } catch (Exception $e) {
            return '<div class="diagnostic-version-info__error">' .
                __('Error loading version information: %1',
                    $this->escaper->escapeHtml($e->getMessage())) .
                '</div>';
        }
    }

    /**
     * Get latest version available from Packagist using V1 API
     *
     * @return string|null
     */
    private function getLatestVersionV1(): ?string
    {
        try {
            $url = sprintf(self::PACKAGIST_V1_API, self::PACKAGE_NAME);

            $this->curl->setTimeout(10);
            $this->curl->get($url);
            $response = $this->curl->getBody();
            $data = $this->json->unserialize($response);

            // Get versions array
            $versions = array_keys($data['package']['versions'] ?? []);

            // Filter and sort
            $stableVersions = array_filter($versions, function ($v) {
                return (bool) preg_match('/^\d+\.\d+\.\d+$/', (string) $v);
            });

            usort($stableVersions, function ($a, $b) {
                return version_compare((string) $a, (string) $b);
            });
            $lastVersion = end($stableVersions);
            return $lastVersion !== false ? (string) $lastVersion : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Check if an update is available
     *
     * @param string $currentVersion
     * @param string|null $latestVersion
     * @return bool
     */
    private function isUpdateAvailable(string $currentVersion, ?string $latestVersion): bool
    {
        // Skip comparison if we couldn't fetch the latest version
        if ($latestVersion === null) {
            return false;
        }

        // Compare versions using version_compare
        return version_compare($currentVersion, $latestVersion, '<');
    }

    /**
     * Get version from composer.json file
     *
     * @param string $installPath
     * @return string|null
     */
    private function getVersionFromComposer(string $installPath): ?string
    {
        try {
            if ($installPath === 'app/code') {
                $composerPath = (defined('BP') ? BP : '') .
                    '/app/code/Bold/CheckoutPaymentBooster/composer.json';
            } else {
                $composerPath = (defined('BP') ? BP : '') .
                    '/vendor/bold-commerce/module-checkout-payment-booster/composer.json';
            }

            if (!$this->fileDriver->isExists($composerPath)) {
                return null;
            }

            $composerContent = $this->fileDriver->fileGetContents($composerPath);
            $composerData = $this->json->unserialize($composerContent);

            return $composerData['version'] ?? null;
        } catch (Exception $e) {
            return null;
        }
    }
}
