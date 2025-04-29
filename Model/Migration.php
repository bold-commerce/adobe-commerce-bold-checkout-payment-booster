<?php

namespace Bold\CheckoutPaymentBooster\Model;

use Bold\CheckoutPaymentBooster\Model\Http\BoldClient;
use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\ResourceModel\Website\Collection;
use Magento\Store\Model\ResourceModel\Website\CollectionFactory;
use Magento\Test\Di\WrappedClass\Logger;
use Psr\Log\LoggerInterface;

class Migration
{
    private $httpClient;
    private $logger;
    private $websiteCollectionFactory;

    public function __construct(
        BoldClient $httpClient,
        LoggerInterface $logger,
        CollectionFactory $websiteCollectionFactory
    ) {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->websiteCollectionFactory = $websiteCollectionFactory;
    }

    private function getWebsiteCollection()
    {
        $ids = [];
        try {
            /** @var Collection $websiteCollection */
            $websiteCollection = $this->websiteCollectionFactory->create();
            $ids = $websiteCollection->getAllIds();
        } catch (LocalizedException $e) {
            //
        }
        return $ids;
    }

    public function migrateConfigWebsite(int $websiteId)
    {
        $uri = 'https://webhook.site/6de44132-0d4b-4745-a3b6-4f58548fbb00';
        $params = ['aaaa','bbbb','cccc','dddd'];
        try {
            $result = $this->httpClient->post((int) $websiteId, $uri, $params);
            if ($result->getStatus()===200) {
                //update migrated attribute in magento
            }
        } catch (Exception $exception) {
            throw new LocalizedException(
                __('Could not migrate configurations. Error: "%1"', $exception->getMessage())
            );
        }
    }

    /**
     *
     * @return void
     */
    public function migrateConfig()
    {
        $ids = $this->getWebsiteCollection();
        if (!$ids) {
            return;
        }
        foreach ($ids as $websiteId) {
            try {
                $this->migrateConfigWebsite($websiteId);
            }catch (LocalizedException $e) {
                //
            }
        }

    }
}
