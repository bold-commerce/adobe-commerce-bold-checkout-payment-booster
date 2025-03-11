<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Block\ShortcutButtons;

use Magento\Catalog\Block\ShortcutInterface;
use Magento\Framework\View\Element\Template;

/**
 * Wallet pay shortcut buttons for the cart page block.
 */
class ExpressPayShortcutButtonsCart extends Template implements ShortcutInterface
{
    public const NAME = 'shortcut.buttons.express_pay.cart';

    protected $_template = 'Bold_CheckoutPaymentBooster::express-pay-cart.phtml';

    /**
     * @inheritDoc
     */
    public function getAlias(): string
    {
        return self::NAME;
    }
}
