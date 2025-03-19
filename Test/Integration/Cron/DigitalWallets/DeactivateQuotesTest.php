<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\Cron\DigitalWallets;

use Bold\CheckoutPaymentBooster\Cron\DigitalWallets\DeactivateQuotes;
use Magento\Cron\Model\ConfigInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class DeactivateQuotesTest extends TestCase
{
    public function testIsConfiguredProperly(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var ConfigInterface $cronConfiguration */
        $cronConfiguration = $objectManager->get(ConfigInterface::class);
        $cronJobs = $cronConfiguration->getJobs();

        self::assertArrayHasKey('bold_booster_deactivate_digital_wallets_quotes', $cronJobs['default']);
        self::assertSame(
            [
                'name' => 'bold_booster_deactivate_digital_wallets_quotes',
                'instance' => DeactivateQuotes::class,
                'method' => 'execute',
                'config_path' =>
                    'crontab/default/jobs/bold_booster_deactivate_digital_wallets_quotes/schedule/cron_expression',
            ],
            $cronJobs['default']['bold_booster_deactivate_digital_wallets_quotes']
        );
    }
}
