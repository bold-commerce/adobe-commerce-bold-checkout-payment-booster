<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\Model\Config\Backend\DigitalWallets;

use Bold\CheckoutPaymentBooster\Observer\Checkout\ConfigureShopObserver;
use Magento\Config\Model\Config;
use Magento\Framework\Exception\LocalizedException;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoAppIsolation enabled
 * @magentoAppArea adminhtml
 */
class DeactivateQuotesTest extends TestCase
{
    public function testValidatesConfigurationSuccessfullyBeforeSaving(): void
    {
        $this->expectNotToPerformAssertions();

        $configData = [
            'section' => 'checkout',
            'website' => null,
            'store' => null,
            'groups' => [
                'bold_checkout_payment_booster_advanced' => [
                    'groups' => [
                        'digital_wallets_quote_cleanup' => [
                            'fields' => [
                                'enabled' => [
                                    'value' => '1',
                                ],
                                'frequency' => [
                                    'value' => 'daily',
                                ],
                                'occurrence' => [
                                    'value' => '7',
                                ],
                                'retention_period' => [
                                    'value' => '5',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $configureShopObserverStub = $this->createStub(ConfigureShopObserver::class);
        $objectManager = Bootstrap::getObjectManager();
        /** @var Config $config */
        $config = $objectManager->create(
            Config::class,
            [
                'data' => $configData,
            ]
        );

        $objectManager->configure(
            [
                ConfigureShopObserver::class => [
                    'shared' => true
                ]
            ]
        );
        // @phpstan-ignore-next-line
        $objectManager->addSharedInstance($configureShopObserverStub, ConfigureShopObserver::class);

        $config->save();
    }

    /**
     * @dataProvider doesNotValidateConfigurationSuccessfullyDataProvider
     */
    public function testDoesNotValidateConfigurationSuccessfullyBeforeSaving(
        string $frequency,
        string $occurrence
    ): void {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage("$occurrence is not a valid $frequency occurrence");

        $configData = [
            'section' => 'checkout',
            'website' => null,
            'store' => null,
            'groups' => [
                'bold_checkout_payment_booster_advanced' => [
                    'groups' => [
                        'digital_wallets_quote_cleanup' => [
                            'fields' => [
                                'enabled' => [
                                    'value' => '1',
                                ],
                                'frequency' => [
                                    'value' => $frequency,
                                ],
                                'occurrence' => [
                                    'value' => $occurrence,
                                ],
                                'retention_period' => [
                                    'value' => '5',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $configureShopObserverStub = $this->createStub(ConfigureShopObserver::class);
        $objectManager = Bootstrap::getObjectManager();
        /** @var Config $config */
        $config = $objectManager->create(
            Config::class,
            [
                'data' => $configData,
            ]
        );

        $objectManager->configure(
            [
                ConfigureShopObserver::class => [
                    'shared' => true
                ]
            ]
        );
        // @phpstan-ignore-next-line
        $objectManager->addSharedInstance($configureShopObserverStub, ConfigureShopObserver::class);

        $config->save();
    }

    /**
     * @return string[][]
     */
    public function doesNotValidateConfigurationSuccessfullyDataProvider(): array
    {
        return [
            'frequency less than one hour' => [
                'frequency' => 'hourly',
                'occurrence' => '0',
            ],
            'frequency greater than 24 hours' => [
                'frequency' => 'hourly',
                'occurrence' => '27',
            ],
            'frequency less than one day' => [
                'frequency' => 'daily',
                'occurrence' => '0',
            ],
            'frequency greater than 31 days' => [
                'frequency' => 'daily',
                'occurrence' => '42',
            ],
        ];
    }
}
