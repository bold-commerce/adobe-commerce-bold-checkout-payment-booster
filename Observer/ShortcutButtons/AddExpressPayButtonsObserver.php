<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Observer\ShortcutButtons;

use Bold\CheckoutPaymentBooster\Block\ShortcutButtons\ExpressPayShortcutButtons;
use Bold\CheckoutPaymentBooster\ViewModel\ExpressPayFactory;
use Magento\Catalog\Block\ShortcutButtons;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;

/**
 * Observes the `shortcut_buttons_container` event
 *
 * @see ShortcutButtons::_beforeToHtml
 */
class AddExpressPayButtonsObserver implements ObserverInterface
{
    /**
     * @var ExpressPayFactory
     */
    private $expressPayFactory;

    public function __construct(ExpressPayFactory $expressPayFactory)
    {
        $this->expressPayFactory = $expressPayFactory;
    }

    public function execute(Observer $observer): void
    {
        $event = $observer->getEvent();
        /** @var ShortcutButtons $container */
        $container = $event->getData('container');
        /** @var ExpressPayShortcutButtons $expressPayShortcutButtons */
        $expressPayShortcutButtons = $container->getLayout()->createBlock(
            ExpressPayShortcutButtons::class,
            ExpressPayShortcutButtons::BLOCK_ALIAS,
            [
                'data' => [
                    'express_pay_view_model' => $this->expressPayFactory->create()
                ]
            ]
        );

        $container->addShortcut($expressPayShortcutButtons);
    }
}
