<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Observer;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Register module for Hyva config.
 */
class RegisterModuleForHyvaConfig implements ObserverInterface
{
    /**
     * @var ComponentRegistrar
     */
    private $componentRegistrar;

    /**
     * @param ComponentRegistrar $componentRegistrar
     */
    public function __construct(ComponentRegistrar $componentRegistrar)
    {
        $this->componentRegistrar = $componentRegistrar;
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $event)
    {
        $config = $event->getData('config');
        $extensions = $config->hasData('extensions') ? $config->getData('extensions') : [];

        $moduleName = implode('_', array_slice(explode('\\', __CLASS__), 0, 2));

        $path = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, $moduleName);

        // Only use the path relative to the Magento base dir
        $extensions[] = ['src' => substr($path, strlen(BP) + 1)];

        $config->setData('extensions', $extensions);
    }
}
