<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model;

use Exception;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Psr\Log\LoggerInterface;

/**
 * Resolve Payment Booster module version from composer.json.
 */
class ModuleVersion
{
    private const FALLBACK_VERSION = 'N/A';

    /**
     * @var DirectoryList
     */
    private $directoryList;

    /**
     * @var File
     */
    private $fileDriver;

    /**
     * @var JsonSerializer
     */
    private $jsonSerializer;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param DirectoryList $directoryList
     * @param File $fileDriver
     * @param JsonSerializer $jsonSerializer
     * @param LoggerInterface $logger
     */
    public function __construct(
        DirectoryList $directoryList,
        File $fileDriver,
        JsonSerializer $jsonSerializer,
        LoggerInterface $logger
    ) {
        $this->directoryList = $directoryList;
        $this->fileDriver = $fileDriver;
        $this->jsonSerializer = $jsonSerializer;
        $this->logger = $logger;
    }

    /**
     * Get semver from composer.json.
     *
     * @return string
     */
    public function get(): string
    {
        try {
            $baseDir = $this->directoryList->getRoot();

            $pathsToCheck = [
                $baseDir . '/vendor/bold-commerce/module-checkout-payment-booster',
                $baseDir . '/app/code/Bold/CheckoutPaymentBooster',
            ];

            foreach ($pathsToCheck as $path) {
                $composerJsonPath = $path . '/composer.json';
                $composerData = $this->parseJsonFile($composerJsonPath);

                if ($composerData !== null && isset($composerData['version'])) {
                    return (string) $composerData['version'];
                }
            }

            return self::FALLBACK_VERSION;
        } catch (Exception $e) {
            $this->logger->error('Failed to get module version: ' . $e->getMessage());
            return self::FALLBACK_VERSION;
        }
    }

    /**
     * Parse JSON file.
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

        $content = $this->fileDriver->fileGetContents($jsonPath);
        $data = $this->jsonSerializer->unserialize($content);

        return is_array($data) ? $data : null;
    }
}
