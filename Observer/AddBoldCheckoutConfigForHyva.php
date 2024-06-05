<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Observer;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Populates the checkoutConfig with the bold config.
 */
class AddBoldCheckoutConfigForHyva implements ObserverInterface
{
    /**
     * @inheritDoc
     */
    public function execute(Observer $event)
    {
        $transport = $event->getData('transport');
        $output = $transport->getData('output');
        $output['bold'] = $transport['checkoutConfig']['bold'] ?? [];
        $transport->setData('output', $output);
    }
}
