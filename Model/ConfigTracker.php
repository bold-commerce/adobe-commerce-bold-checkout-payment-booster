<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model;

use Exception;
use Magento\Framework\Serialize\SerializerInterface;
use Psr\Log\LoggerInterface;
use Bold\CheckoutPaymentBooster\Model\SimpleDiagnosticService as DiagnosticService;

/**
 * Configuration Tracker Service
 * Tracks changes in core_config_data to determine if diagnostic data should be sent
 */
class ConfigTracker
{
    /** @var DiagnosticService */
    private $diagnosticService;

    /** @var LoggerInterface */
    private $logger;

    /** @var SerializerInterface */
    private $serializer;

    /**
     * Constructor
     *
     * @param LoggerInterface $logger
     * @param SerializerInterface $serializer
     * @param DiagnosticService $diagnosticService
     */
    public function __construct(
        LoggerInterface       $logger,
        SerializerInterface   $serializer,
        DiagnosticService     $diagnosticService
    ) {
        $this->diagnosticService = $diagnosticService;
        $this->logger = $logger;
        $this->serializer = $serializer;
    }

    /**
     * Get current diagnostic data as JSON string
     *
     * @return string
     */
    public function getCurrentDiagnosticData(): string
    {
        try {
            $diagnosticData = $this->diagnosticService->generateDiagnosticData();
            return $this->convertDiagnosticDataToJson($diagnosticData);
        } catch (Exception $e) {
            $this->logger->error('Error getting current diagnostic data: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Check if configuration data has changed since last send
     *
     * @param string $savedDiagnosticData
     * @param string $currentDiagnosticData
     * @return bool
     */
    public function hasConfigurationChanged(
        string $savedDiagnosticData,
        string $currentDiagnosticData
    ): bool {
        try {
            $this->logger->info('Checking if configuration has changed');

            if (empty($savedDiagnosticData)) {
                $this->logger->info('No previous configuration data found, considering as changed');
                return true;
            }

            $currentDiagnosticArray = $this->convertJsonToArray($currentDiagnosticData);
            $savedDiagnosticArray = $this->convertJsonToArray($savedDiagnosticData);

            if ($currentDiagnosticArray || $savedDiagnosticArray) {
                $this->logger->info('Data arrays are not valid, considering as changed');
                return true;
            }
            return $this->hasArrayChanges($currentDiagnosticArray, $savedDiagnosticArray);
        } catch (Exception $e) {
            $this->logger->error('Error checking configuration changes: ' . $e->getMessage());
            return true;
        }
    }

    /**
     * Convert diagnostic data object to JSON string
     *
     * @param array $diagnosticData
     * @return string
     * @phpstan-param array<string, mixed> $diagnosticData
     */
    private function convertDiagnosticDataToJson(array $diagnosticData): string
    {
        try {
            $data = $this->getBaseFromConfigurationData($diagnosticData);

            // Add diagnostic-specific data
            $data['success'] = $diagnosticData['success'];
            $data['timestamp'] = $diagnosticData['timestamp'];

            $result = $this->serializer->serialize($data);
            return is_string($result) ? $result : '';
        } catch (Exception $e) {
            $this->logger->error('Error converting diagnostic data to JSON: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Convert json to array
     *
     * @param string $json
     * @return array
     * @phpstan-return array<string, mixed>
     */
    private function convertJsonToArray(string $json): array
    {
        try {
            $convertedResult = $this->serializer->unserialize($json);
            if (!is_array($convertedResult)) {
                $this->logger->error('Error converting json to array: Result is not an array');
                return [];
            }
            return $convertedResult;
        } catch (Exception $e) {
            $this->logger->error('Error converting json to array: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Extract base configuration data (platform, store_info, bold_config)
     *
     * This is the common data used by both diagnostic and configuration JSON conversion
     *
     * @param array $diagnosticData
     * @return array
     * @phpstan-param array<string, mixed> $diagnosticData
     * @phpstan-return array<string, mixed>
     */
    private function getBaseFromConfigurationData(array $diagnosticData): array
    {
        return [
            'platform' => $diagnosticData['platform'],
            'store_info' => $diagnosticData['store_info'],
            'bold_config' => $diagnosticData['bold_config']
        ];
    }

    /**
     * Check for changes between two arrays recursively
     *
     * @param array $old
     * @param array $new
     * @return bool
     * @phpstan-param array<string, mixed> $old
     * @phpstan-param array<string, mixed> $new
     */
    private function hasArrayChanges(array $old, array $new): bool
    {
        //Ignoring timestamp since it will always be different
        $ignoredKeys = ['timestamp'];

        $oldFiltered = array_diff_key($old, array_flip($ignoredKeys));
        $newFiltered = array_diff_key($new, array_flip($ignoredKeys));

        if (count($oldFiltered) !== count($newFiltered)) {
            return true;
        }

        foreach ($oldFiltered as $key => $value) {
            if (!array_key_exists($key, $newFiltered)) {
                return true;
            }

            if (is_array($value) && is_array($newFiltered[$key])) {
                if ($this->hasArrayChanges($value, $newFiltered[$key])) {
                    return true;
                }
            } elseif ($value !== $newFiltered[$key]) {
                return true;
            }
        }
        return false;
    }
}
