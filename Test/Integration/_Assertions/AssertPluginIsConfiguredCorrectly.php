<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\_Assertions;

use Magento\Framework\Interception\PluginList\PluginList;
use Magento\TestFramework\Helper\Bootstrap;

trait AssertPluginIsConfiguredCorrectly
{
    /**
     * @param class-string $instance
     * @param class-string $target
     */
    private static function assertPluginIsConfiguredCorrectly(
        string $pluginName,
        string $instance,
        string $target,
        int $sortOrder = 0
    ): void {
        $objectManager = Bootstrap::getObjectManager();
        /** @var PluginList $pluginList */
        $pluginList = $objectManager->create(PluginList::class);
        /** @var array<string, array{sortOrder: int, instance: class-string}> $plugins */
        $plugins = $pluginList->get($target, []);

        self::assertArrayHasKey($pluginName, $plugins);
        self::assertSame($sortOrder, $plugins[$pluginName]['sortOrder']);
        self::assertSame($instance, $plugins[$pluginName]['instance']);
    }
}
