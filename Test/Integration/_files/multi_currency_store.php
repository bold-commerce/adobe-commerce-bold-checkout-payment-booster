<?php

declare(strict_types=1);

use Magento\Config\App\Config\Type\System as Config;
use Magento\Config\Model\ResourceModel\Config as ConfigResource;
use Magento\Directory\Model\Currency;
use Magento\Directory\Model\ResourceModel\Currency as CurrencyResource;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;

$objectManager = Bootstrap::getObjectManager();

/** @var Store $store */
$store = $objectManager->create(Store::class);
$store->load('default', 'code');
$storeId = (int) $store->getId();

/** @var ConfigResource $configResource */
$configResource = $objectManager->get(ConfigResource::class);
$configResource->saveConfig(Currency::XML_PATH_CURRENCY_BASE, 'USD', ScopeInterface::SCOPE_STORES, $storeId);
$configResource->saveConfig(Currency::XML_PATH_CURRENCY_DEFAULT, 'USD', ScopeInterface::SCOPE_STORES, $storeId);
$configResource->saveConfig(
    Currency::XML_PATH_CURRENCY_ALLOW,
    'USD,EUR,GBP',
    ScopeInterface::SCOPE_STORES,
    $storeId
);
$configResource->saveConfig(Currency::XML_PATH_CURRENCY_BASE, 'USD', ScopeInterface::SCOPE_DEFAULT, 0);
$configResource->saveConfig(Currency::XML_PATH_CURRENCY_DEFAULT, 'USD', ScopeInterface::SCOPE_DEFAULT, 0);
$configResource->saveConfig(Currency::XML_PATH_CURRENCY_ALLOW, 'USD,EUR,GBP', ScopeInterface::SCOPE_DEFAULT, 0);

/** Configuration cache clean is required to reload currency settings. */
/** @var Config $config */
$config = $objectManager->get(Config::class);
$config->clean();

$store->unsetData('available_currency_codes');
$store->unsetData('current_currency');

/** @var StoreManagerInterface $storeManager */
$storeManager = $objectManager->get(StoreManagerInterface::class);
$storeManager->getStore($storeId)->unsetData('available_currency_codes');
$storeManager->getStore($storeId)->unsetData('current_currency');

/** @var CurrencyResource $currencyResource */
$currencyResource = $objectManager->create(CurrencyResource::class);
$currencyResource->saveRates(
    [
        'USD' => [
            'USD' => 1.0,
            'EUR' => 0.92,
            'GBP' => 0.79,
        ],
        'EUR' => [
            'EUR' => 1.0,
            'USD' => 1.08695652,
            'GBP' => 0.85869565,
        ],
        'GBP' => [
            'GBP' => 1.0,
            'USD' => 1.26582278,
            'EUR' => 1.16455696,
        ],
    ]
);
