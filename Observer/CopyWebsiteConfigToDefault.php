<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Observer;

use Exception;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Cache\TypeListInterface as CacheTypeList;
use Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory as ConfigCollectionFactory;
use Psr\Log\LoggerInterface;

/**
 * Class responsible for copying website scope values to default scope
 */
class CopyWebsiteConfigToDefault implements ObserverInterface
{
    /** @var CacheTypeList */
    private $cacheTypeList;

    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var ConfigCollectionFactory */
    private $configCollectionFactory;

    /** @var WriterInterface */
    private $configWriter;

    /** @var LoggerInterface */
    private $logger;

    /**
     * Constructor
     *
     * @param CacheTypeList $cacheTypeList
     * @param ConfigCollectionFactory $configCollectionFactory
     * @param WriterInterface $configWriter
     * @param LoggerInterface $logger
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        CacheTypeList $cacheTypeList,
        ConfigCollectionFactory     $configCollectionFactory,
        WriterInterface       $configWriter,
        LoggerInterface       $logger,
        StoreManagerInterface $storeManager
    ) {
        $this->cacheTypeList = $cacheTypeList;
        $this->configCollectionFactory = $configCollectionFactory;
        $this->configWriter = $configWriter;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
    }

    /**
     * Execute method
     */
    public function execute(Observer $observer)
    {
        if (!$this->storeManager->isSingleStoreMode()) {
            return;
        }
        try {
            $websiteId = $this->storeManager->getDefaultStoreView()->getWebsiteId();

            $collection = $this->configCollectionFactory->create()
                ->addFieldToFilter('scope', 'websites')
                ->addFieldToFilter('scope_id', (string) $websiteId)
                ->addFieldToFilter('path', ['like' => 'checkout/bold_checkout_payment_booster%']);

            if ($collection->getSize() === 0) {
                return;
            }

            foreach ($collection->getItems() as $config) {
                $path = $config->getDataByKey('path');
                $value = $config->getDataByKey('value');

                $this->configWriter->save(
                    $path,
                    $value,
                    ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                    0
                );
            }
            $this->cacheTypeList->cleanType('config');
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }
}
