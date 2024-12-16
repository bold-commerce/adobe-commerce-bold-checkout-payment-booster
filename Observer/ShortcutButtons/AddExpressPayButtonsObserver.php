<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Observer\ShortcutButtons;

use Bold\CheckoutPaymentBooster\Block\ShortcutButtons\ExpressPayShortcutButtons;
use Bold\CheckoutPaymentBooster\ViewModel\ExpressPayFactory;
use Bold\CheckoutPaymentBooster\UI\PaymentBoosterConfigProvider;
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

        $layout = $container->getLayout();

        if ($layout->getBlock(ExpressPayShortcutButtons::BLOCK_ALIAS) !== false) {
            return;
        }

        /** @var ExpressPayShortcutButtons $expressPayShortcutButtons */
        $expressPayShortcutButtons = $layout->createBlock(
            ExpressPayShortcutButtons::class,
            '',
            [
                'data' => [
                    'express_pay_view_model' => $this->expressPayFactory->create(),
                    'render_page_source' => $this->getPageType($observer->getEvent())
                ]
            ]
        );

        $container->addShortcut($expressPayShortcutButtons);
    }

    /**
     * @param $event
     * @return string
     */
    private function getPageType($event) : string
    {
        if ($event->getIsCatalogProduct()) {
            return PaymentBoosterConfigProvider::PAGE_SOURCE_PRODUCT;
        }
        if ($event->getIsShoppingCart()) {
            return PaymentBoosterConfigProvider::PAGE_SOURCE_CART;
        }
        return PaymentBoosterConfigProvider::PAGE_SOURCE_MINICART;
    }
}
