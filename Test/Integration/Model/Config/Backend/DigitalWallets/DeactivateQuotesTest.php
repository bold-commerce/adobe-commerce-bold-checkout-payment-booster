<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\Model\Config\Backend\DigitalWallets;

use Bold\CheckoutPaymentBooster\Observer\Checkout\ConfigureShopObserver;
use Magento\Config\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\ValueFactory;
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
        $objectManager->addSharedInstance($configureShopObserverStub, ConfigureShopObserver::class);

        $config->save();
    }

    /**
     * @dataProvider savesCronExpressionSuccessfullyDataProvider
     */
    public function testSavesCronExpressionConfigurationSuccessfullyAfterSaving(
        string $frequency,
        ?string $occurrence,
        ?string $sequence
    ): void {
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
                                'retention_period' => [
                                    'value' => '1',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        if ($occurrence !== null) {
            $configData['groups']['bold_checkout_payment_booster_advanced']['groups']['digital_wallets_quote_cleanup']
                ['fields']['occurrence'] = [
                    'value' => $occurrence,
                ];
        }

        if ($sequence !== null) {
            $configData['groups']['bold_checkout_payment_booster_advanced']['groups']['digital_wallets_quote_cleanup']
                ['fields']['sequence'] = [
                    'value' => $sequence,
                ];
        }

        $configureShopObserverStub = $this->createStub(ConfigureShopObserver::class);
        $objectManager = Bootstrap::getObjectManager();
        /** @var Config $config */
        $config = $objectManager->create(
            Config::class,
            [
                'data' => $configData,
            ]
        );
        /** @var ScopeConfigInterface $scopeConfig */
        $scopeConfig = $objectManager->create(ScopeConfigInterface::class);

        $objectManager->configure(
            [
                ConfigureShopObserver::class => [
                    'shared' => true
                ]
            ]
        );
        $objectManager->addSharedInstance($configureShopObserverStub, ConfigureShopObserver::class);

        $config->save();

        $expectedCronExpression = $sequence ?? (
            $frequency === 'hourly' ? "0 */$occurrence * * *" : "0 0 */$occurrence * *"
        );
        $actualCronExpression = $scopeConfig->getValue(
            'crontab/default/jobs/bold_booster_deactivate_digital_wallets_quotes/schedule/cron_expression'
        );

        self::assertSame($expectedCronExpression, $actualCronExpression);
    }

    public function testDoesNotSaveCronExpressionConfigurationSuccessfullyAfterSaving(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Could not save the Cron expression.');

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
                                    'value' => '1',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $configureShopObserverStub = $this->createStub(ConfigureShopObserver::class);
        $valueFactoryStub = $this->createStub(ValueFactory::class);
        $objectManager = Bootstrap::getObjectManager();
        /** @var LocalizedException $localizedException */
        $localizedException = $objectManager->create(
            LocalizedException::class,
            [
                'phrase' => __('Invalid configuration path')
            ]
        );
        /** @var Config $config */
        $config = $objectManager->create(
            Config::class,
            [
                'data' => $configData,
            ]
        );

        $valueFactoryStub
            ->method('create')
            ->willThrowException($localizedException);

        $objectManager->configure(
            [
                ConfigureShopObserver::class => [
                    'shared' => true
                ]
            ]
        );
        $objectManager->configure(
            [
                ValueFactory::class => [
                    'shared' => true
                ]
            ]
        );
        $objectManager->addSharedInstance($configureShopObserverStub, ConfigureShopObserver::class);
        $objectManager->addSharedInstance($valueFactoryStub, ValueFactory::class);

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

    /**
     * @return array<string, array<string, string|null>>
     */
    public function savesCronExpressionSuccessfullyDataProvider(): array
    {
        return [
            'frequency of two hours' => [
                'frequency' => 'hourly',
                'occurrence' => '2',
                'sequence' => null,
            ],
            'frequency of two days' => [
                'frequency' => 'daily',
                'occurrence' => '2',
                'sequence' => null,
            ],
            'custom frequency, once monthly' => [
                'frequency' => 'custom',
                'occurrence' => null,
                'sequence' => '0 0 1 */1 *',
            ],
        ];
    }
}
