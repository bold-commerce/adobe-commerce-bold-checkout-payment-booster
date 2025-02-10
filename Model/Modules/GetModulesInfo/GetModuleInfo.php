<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Modules\GetModulesInfo;

use Bold\CheckoutPaymentBooster\Api\Data\Module\InfoInterface;
use Bold\CheckoutPaymentBooster\Api\Data\Module\InfoInterfaceFactory;
use Exception;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Module\Dir\Reader;
use Magento\Framework\Serialize\SerializerInterface;

class GetModuleInfo
{
    /**
     * @var Reader
     */
    private $reader;

    /**
     * @var File
     */
    private $filesystem;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var InfoInterfaceFactory
     */
    private $moduleInfoFactory;

    /**
     * @param Reader $reader
     * @param File $filesystem
     * @param SerializerInterface $serializer
     */
    public function __construct(
        Reader $reader,
        File $filesystem,
        SerializerInterface $serializer,
        InfoInterfaceFactory $moduleInfoFactory
    ) {
        $this->reader = $reader;
        $this->filesystem = $filesystem;
        $this->serializer = $serializer;
        $this->moduleInfoFactory = $moduleInfoFactory;
    }

    /**
     * Get composer package info by module name.
     *
     * @param string $moduleName
     * @return InfoInterface
     * @throws Exception
     */
    public function getInfo(string $moduleName): InfoInterface
    {
        $directoryPath = $this->reader->getModuleDir('', $moduleName);
        $dataPath = $directoryPath . '/composer.json';
        $data = $this->filesystem->fileGetContents($dataPath);
        $composerData = $this->serializer->unserialize($data);
        $name = $composerData['name'] ?? $moduleName;
        $version = $composerData['version'] ?? 'n/a';

        return $this->moduleInfoFactory->create(
            [
                'name' => $name,
                'version' => $version,
            ]
        );
    }
}
