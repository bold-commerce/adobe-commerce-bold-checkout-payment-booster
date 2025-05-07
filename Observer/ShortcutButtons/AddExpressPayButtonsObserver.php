<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Observer\ShortcutButtons;

use Bold\CheckoutPaymentBooster\Block\ShortcutButtons\ExpressPayShortcutButtonsCart;
use Bold\CheckoutPaymentBooster\Block\ShortcutButtons\ExpressPayShortcutButtonsMiniCart;
use Bold\CheckoutPaymentBooster\Block\ShortcutButtons\ExpressPayShortcutButtonsProduct;
use Bold\CheckoutPaymentBooster\Model\Config;
use Bold\CheckoutPaymentBooster\ViewModel\ExpressPay as ExpressPayViewModel;
use Bold\CheckoutPaymentBooster\ViewModel\ExpressPayFactory as ExpressPayViewModelFactory;
use Magento\Catalog\Block\ShortcutButtons;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\View\Element\BlockInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\LayoutInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Observes the `shortcut_buttons_container` event
 *
 * @see ShortcutButtons::_beforeToHtml
 */
class AddExpressPayButtonsObserver implements ObserverInterface
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var ExpressPayViewModelFactory
     */
    private $expressPayViewModelFactory;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param StoreManagerInterface $storeManager
     * @param ExpressPayViewModelFactory $expressPayViewModelFactory
     * @param Config $config
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ExpressPayViewModelFactory $expressPayViewModelFactory,
        Config $config
    ) {
        $this->storeManager = $storeManager;
        $this->expressPayViewModelFactory = $expressPayViewModelFactory;
        $this->config = $config;
    }

    /**
     * Add Digital Wallets shortcut buttons to the product, cart and mini cart pages
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $event = $observer->getEvent();
        $container = $event->getData('container');
        $layout = $container->getLayout();
        $websiteId = (int)$this->storeManager->getWebsite()->getId();
        if ($this->isProduct($event, $websiteId)) {
            $this->addCatalogProductShortcut($layout, $container);
        }
        if ($this->isCart($event, $websiteId)) {
            $this->addCartShortcut($layout, $container);
        }
        if ($this->isMiniCart($event, $websiteId)) {
            $this->addMiniCartShortcut($layout, $container);
        }
    }

    /**
     * Add Digital Wallets shortcut to the product page.
     *
     * @param LayoutInterface $layout
     * @param ShortcutButtons $container
     */
    private function addCatalogProductShortcut(
        LayoutInterface $layout,
        ShortcutButtons $container
    ): void {
        /** @var ExpressPayViewModel $expressPayViewModel */
        $expressPayViewModel = $this->expressPayViewModelFactory->create();
        /** @var BlockInterface&Template $shortCut */
        $shortCut = $layout->createBlock(
            ExpressPayShortcutButtonsProduct::class,
            ExpressPayShortcutButtonsProduct::NAME,
            [
                'data' => [
                    'express_pay_view_model' => $expressPayViewModel,
                ]
            ]
        );
        $container->addShortcut($shortCut);
    }

    /**
     * Add Digital Wallets shortcut to the cart page.
     *
     * @param LayoutInterface $layout
     * @param ShortcutButtons $container
     */
    private function addCartShortcut(LayoutInterface $layout, ShortcutButtons $container): void
    {
        /** @var BlockInterface&Template $cartShortcut */
        $cartShortcut = $layout->createBlock(
            ExpressPayShortcutButtonsCart::class,
            ExpressPayShortcutButtonsCart::NAME
        );
        $container->addShortcut($cartShortcut);
    }

    /**
     * Add Digital Wallets shortcut to the mini cart.
     *
     * @param LayoutInterface $layout
     * @param ShortcutButtons $container
     * @return void
     */
    private function addMiniCartShortcut(LayoutInterface $layout, ShortcutButtons $container): void
    {
        /** @var ExpressPayViewModel $expressPayViewModel */
        $expressPayViewModel = $this->expressPayViewModelFactory->create();
        /** @var BlockInterface&Template $miniCartShortcut */
        $miniCartShortcut = $layout->createBlock(
            ExpressPayShortcutButtonsMiniCart::class,
            ExpressPayShortcutButtonsMiniCart::NAME,
            [
                'data' => [
                    'express_pay_view_model' => $expressPayViewModel,
                ]
            ]
        );
        $container->addShortcut($miniCartShortcut);
    }

    /**
     * Verify if Digital Wallets shortcut should be added to the product page.
     *
     * @param Event $event
     * @param int $websiteId
     * @return bool
     */
    private function isProduct(Event $event, int $websiteId): bool
    {
        $position = $event->getData('or_position');
        if ($position === 'after') {
            // prevent broken page in case product widgets are used.
            return false;
        }
        return $event->getIsCatalogProduct() && $this->config->isProductWalletPayEnabled($websiteId);
    }

    /**
     * Verify if Digital Wallets shortcut should be added to the cart page.
     *
     * @param Event $event
     * @param int $websiteId
     * @return bool
     */
    private function isCart(Event $event, int $websiteId): bool
    {
        return $event->getIsShoppingCart() && $this->config->isCartWalletPayEnabled($websiteId);
    }

    /**
     * Verify if Digital Wallets shortcut should be added to the mini cart.
     *
     * @param Event $event
     * @param int $websiteId
     * @return bool
     */
    private function isMiniCart(Event $event, int $websiteId): bool
    {
        return !$event->getIsShoppingCart()
            && !$event->getIsCatalogProduct()
            && $this->config->isCartWalletPayEnabled($websiteId);
    }
}
