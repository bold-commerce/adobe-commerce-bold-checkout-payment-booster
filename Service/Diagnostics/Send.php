<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Service\Diagnostics;

use Bold\CheckoutPaymentBooster\Model\Config;
use Bold\CheckoutPaymentBooster\Model\ConfigTracker;
use Bold\CheckoutPaymentBooster\Model\Http\BoldClient;
use Exception;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

/**
 * Service class for sending diagnostic data to Bold API
 */
class Send
{
    /** @var string  */
    private const NO_DATA_FOUND = 'No diagnostic data found';

    private const SUCCESS_MESSAGE = 'Diagnostic data sent successfully to Bold API';

    private const ERROR_MESSAGE = 'Diagnostic data sent failed to Bold API';

    /** @var Config */
    private $config;

    /** @var ConfigTracker */
    private $configTracker;

    /** @var BoldClient */
    private $boldClient;

    /** @var Json */
    private $jsonSerializer;

    /** @var LoggerInterface */
    private $logger;

    /**
     * @param Config $config
     * @param ConfigTracker $configTracker
     * @param BoldClient $boldClient
     * @param Json $jsonSerializer
     * @param LoggerInterface $logger
     */
    public function __construct(
        Config $config,
        ConfigTracker $configTracker,
        BoldClient $boldClient,
        Json $jsonSerializer,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->configTracker = $configTracker;
        $this->boldClient = $boldClient;
        $this->jsonSerializer = $jsonSerializer;
        $this->logger = $logger;
    }

    /**
     * Send diagnostic data to Bold API
     *
     * @param int|null $websiteId Optional website ID, if null will use current store
     * @return array
     * @phpstan-return array<string, mixed>
     */
    public function sendDiagnosticData(?int $websiteId = null): array
    {
        try {
            if ($websiteId === null) {
                $websiteId = $this->config->getCurrentWebsiteId();
            }

            $savedDiagnosticData = $this->config->getSavedDiagnosticData($websiteId) ?? '';
            $currentDiagnosticData = $this->configTracker->getCurrentDiagnosticData() ?? '';

            $hasChanged = $this->configTracker->hasConfigurationChanged($savedDiagnosticData, $currentDiagnosticData);

            if (!$hasChanged) {
                $this->logger->info('No configuration changes detected, skipping diagnostic data send');
                return [
                    'success' => false,
                    'message' => 'No configuration changes detected. Diagnostic data will not be sent.'
                ];
            }

            $this->logger->info('Configuration changes detected, sending diagnostic data to Bold');

            $this->logger->info('Using website ID for diagnostic send: ' . $websiteId);

            $shopId = $this->config->getShopId((int) $websiteId);
            if (empty($shopId)) {
                return [
                    'success' => false,
                    'message' => 'Shop ID not configured'
                ];
            }

            $diagnosticJson = $this->configTracker->getCurrentDiagnosticData();
            if (!$diagnosticJson) {
                $this->logger->error('Failed to get diagnostic data', [
                    'shop_id' => $shopId,
                    'error' => self::NO_DATA_FOUND
                ]);

                return [
                    'success' => false,
                    'message' => 'Failed to send diagnostic data: ' . self::NO_DATA_FOUND
                ];
            }

            $diagnosticData = $this->jsonSerializer->unserialize($diagnosticJson);

            if (!is_array($diagnosticData)) {
                $this->logger->error('Failed to parse diagnostic data', [
                    'shop_id' => $shopId,
                    'error' => 'Invalid data format'
                ]);

                return [
                    'success' => false,
                    'message' => 'Failed to send diagnostic data: Invalid data format'
                ];
            }

            $uri = 'checkout/shop/{{shopId}}/internal/diagnostic';

            $response = $this->sendToBoldApi((int) $websiteId, $uri, $diagnosticData);

            if ($response['success']) {
                $this->logger->info('Diagnostic data successfully sent to Bold API');
                $this->config->saveLastSentDiagnosticData($diagnosticJson);
                return [
                    'success' => true,
                    'message' => self::SUCCESS_MESSAGE
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to send diagnostic data: ' . ($response['message'] ?? 'Unknown error')
                ];
            }
        } catch (Exception $e) {
            $this->logger->error(self::ERROR_MESSAGE, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send data to Bold API using BoldClient.
     *
     * @param int $websiteId
     * @param string $url
     * @param array $data
     * @return array
     * @phpstan-param array<string, mixed> $data
     * @phpstan-return array<string, mixed>
     */
    private function sendToBoldApi(int $websiteId, string $url, array $data): array
    {
        try {
            $result = $this->boldClient->post($websiteId, $url, $data);

            if ($result->getErrors()) {
                $message = isset(current($result->getErrors())['message'])
                    ? current($result->getErrors())['message']
                    : 'The diagnostic data cannot be sent.';

                return [
                    'success' => false,
                    'error' => $message
                ];
            }

            return [
                'success' => true,
                'response' => $result->getBody()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Exception: ' . $e->getMessage()
            ];
        }
    }
}
